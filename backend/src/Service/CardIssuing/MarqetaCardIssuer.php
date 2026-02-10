<?php

declare(strict_types=1);

namespace App\Service\CardIssuing;

use App\Service\OpenBankingException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Marqeta card issuing — Visa debit cards for EU Pay.
 *
 * Marqeta is a licensed card programme manager in the EU (via Marqeta Europe Ltd,
 * authorized by the Central Bank of Ireland). Powers: Curve, Monese, Wise, Block.
 *
 * Architecture:
 *   EU Pay app → EU Pay backend → Marqeta API → Visa network → POS terminal
 *
 * For NFC tap-to-pay:
 *   1. createVirtualCard() → Marqeta issues Visa card with PAN
 *   2. provisionDigitalCard() → Marqeta creates DPAN (Device PAN) for HCE
 *   3. generateEmvSessionKeys() → Marqeta provides ARQC for each tap
 *   4. Android HCE service sends APDU with DPAN + ARQC to POS terminal
 *   5. POS → acquirer → Visa → Marqeta → EU Pay webhook → settlement
 *
 * @see https://www.marqeta.com/docs/core-api
 */
class MarqetaCardIssuer implements CardIssuerInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $baseUrl,
        private readonly string $applicationToken,
        private readonly string $adminAccessToken,
        private readonly string $cardProductToken,
        private readonly string $fundingSourceToken,
        private readonly LoggerInterface $logger,
    ) {}

    // ── Card Lifecycle ──────────────────────────

    public function createVirtualCard(string $userId, string $cardholderName, string $currency = 'EUR'): array
    {
        // Step 1: Ensure Marqeta user exists
        $marqetaUser = $this->ensureUser($userId, $cardholderName);

        // Step 2: Create virtual card
        $response = $this->request('POST', '/v3/cards', [
            'user_token' => $marqetaUser['token'],
            'card_product_token' => $this->cardProductToken,
            'fulfillment' => [
                'card_fulfillment_reason' => 'DIGITALLY_PRESENTED',
                'card_personalization' => [
                    'text' => [
                        'name_line_1' => ['value' => $cardholderName],
                    ],
                ],
            ],
        ]);

        $this->logger->info('Marqeta virtual card created', [
            'cardToken' => $response['token'],
            'last4' => $response['last_four'],
        ]);

        return [
            'cardId' => $response['token'],
            'status' => $response['state'],
            'maskedPan' => $response['pan'] ?? ('************' . $response['last_four']),
            'last4' => $response['last_four'],
            'expiryMonth' => (int) $response['expiration_time_month'] ?? 12,
            'expiryYear' => (int) $response['expiration_time_year'] ?? 2028,
            'scheme' => 'VISA',
        ];
    }

    public function activateCard(string $cardId): array
    {
        $response = $this->request('POST', "/v3/cards/{$cardId}/activate");
        return ['cardId' => $cardId, 'status' => $response['state']];
    }

    public function blockCard(string $cardId): array
    {
        $response = $this->request('PUT', "/v3/cards/{$cardId}", [
            'state' => 'SUSPENDED',
            'reason_code' => '08', // Requested by cardholder
            'reason' => 'Blocked by user via EU Pay',
        ]);
        return ['cardId' => $cardId, 'status' => $response['state']];
    }

    public function unblockCard(string $cardId): array
    {
        $response = $this->request('PUT', "/v3/cards/{$cardId}", [
            'state' => 'ACTIVE',
        ]);
        return ['cardId' => $cardId, 'status' => $response['state']];
    }

    public function terminateCard(string $cardId): array
    {
        $response = $this->request('PUT', "/v3/cards/{$cardId}", [
            'state' => 'TERMINATED',
            'reason_code' => '00',
        ]);
        return ['cardId' => $cardId, 'status' => 'TERMINATED'];
    }

    public function getCard(string $cardId): array
    {
        $response = $this->request('GET', "/v3/cards/{$cardId}");
        return [
            'cardId' => $response['token'],
            'status' => $response['state'],
            'last4' => $response['last_four'],
            'expiryMonth' => (int) $response['expiration_time_month'],
            'expiryYear' => (int) $response['expiration_time_year'],
            'scheme' => 'VISA',
        ];
    }

    // ── NFC Tokenization ────────────────────────

    public function provisionDigitalCard(
        string $cardId,
        string $deviceId,
        string $deviceFingerprint,
    ): array {
        // Create digital wallet token (DPAN) for HCE
        $response = $this->request('POST', '/v3/digitalwallettokens', [
            'card_token' => $cardId,
            'device' => [
                'token' => $deviceId,
                'type' => 'MOBILE_PHONE',
                'device_fingerprint' => $deviceFingerprint,
            ],
            'wallet_provider_profile' => [
                'wallet_provider' => 'ECOMMERCE', // HCE = ecommerce profile
            ],
            'token_service_provider' => 'VISA_TOKEN_SERVICE',
        ]);

        // Get EMV key material for offline authentication
        $emvKeys = $this->request('GET', "/v3/digitalwallettokens/{$response['token']}/emvkeys");

        $this->logger->info('Marqeta DPAN provisioned for HCE', [
            'cardToken' => $cardId,
            'dpanToken' => $response['token'],
        ]);

        return [
            'dpan' => $response['digital_card_token']['pan'] ?? '',
            'dpanExpiryMonth' => (int) ($response['digital_card_token']['exp_month'] ?? 12),
            'dpanExpiryYear' => (int) ($response['digital_card_token']['exp_year'] ?? 2028),
            'tokenReferenceId' => $response['token'],
            'tokenStatus' => $response['state'] ?? 'ACTIVE',
            'emvKeys' => [
                'iccPrivateKey' => $emvKeys['icc_private_key'] ?? '',
                'iccCertificate' => $emvKeys['icc_certificate'] ?? '',
                'issuerPublicKey' => $emvKeys['issuer_public_key'] ?? '',
            ],
        ];
    }

    public function generateEmvSessionKeys(string $tokenReferenceId, int $currentAtc): array
    {
        $response = $this->request('POST', "/v3/digitalwallettokens/{$tokenReferenceId}/emvsession", [
            'atc' => $currentAtc + 1,
        ]);

        return [
            'sessionKey' => $response['session_key'],
            'arqc' => $response['arqc'],
            'atc' => (int) $response['atc'],
            'unpredictableNumber' => $response['unpredictable_number'] ?? bin2hex(random_bytes(4)),
        ];
    }

    public function deactivateDigitalCard(string $tokenReferenceId): array
    {
        $response = $this->request('PUT', "/v3/digitalwallettokens/{$tokenReferenceId}", [
            'state' => 'TERMINATED',
        ]);
        return ['tokenReferenceId' => $tokenReferenceId, 'status' => 'TERMINATED'];
    }

    public function getDigitalCardStatus(string $tokenReferenceId): array
    {
        $response = $this->request('GET', "/v3/digitalwallettokens/{$tokenReferenceId}");
        return [
            'tokenReferenceId' => $response['token'],
            'status' => $response['state'],
        ];
    }

    // ── Funding ─────────────────────────────────

    public function loadFunds(string $cardId, int $amountCents, string $currency = 'EUR'): array
    {
        $response = $this->request('POST', '/v3/gpaorders', [
            'user_token' => $this->getUserTokenByCard($cardId),
            'amount' => $amountCents / 100,
            'currency_code' => $currency,
            'funding_source_token' => $this->fundingSourceToken,
            'memo' => 'EU Pay top-up',
        ]);

        return [
            'transactionId' => $response['token'],
            'amount' => $response['amount'],
            'status' => $response['state'],
        ];
    }

    public function getCardBalance(string $cardId): array
    {
        $response = $this->request('GET', "/v3/cards/{$cardId}/balances");
        return [
            'available' => (int) (($response['gpa']['available_balance'] ?? 0) * 100),
            'ledger' => (int) (($response['gpa']['ledger_balance'] ?? 0) * 100),
            'currency' => 'EUR',
        ];
    }

    // ── Internal ────────────────────────────────

    private function ensureUser(string $userId, string $name): array
    {
        try {
            return $this->request('GET', "/v3/users/{$userId}");
        } catch (OpenBankingException $e) {
            if ($e->getCode() === 404) {
                return $this->request('POST', '/v3/users', [
                    'token' => $userId,
                    'first_name' => $name,
                    'last_name' => '',
                    'active' => true,
                ]);
            }
            throw $e;
        }
    }

    private function getUserTokenByCard(string $cardId): string
    {
        $card = $this->request('GET', "/v3/cards/{$cardId}");
        return $card['user_token'];
    }

    private function request(string $method, string $path, array $body = []): array
    {
        $options = [
            'auth_basic' => [$this->applicationToken, $this->adminAccessToken],
            'headers' => ['Content-Type' => 'application/json'],
        ];

        if (!empty($body) && $method !== 'GET') {
            $options['json'] = $body;
        }

        try {
            $response = $this->httpClient->request($method, $this->baseUrl . $path, $options);
            return $response->toArray();
        } catch (\Throwable $e) {
            $this->logger->error('Marqeta API error', [
                'method' => $method,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            throw new OpenBankingException(
                "Marqeta API error: {$e->getMessage()}",
                (int) $e->getCode(),
                $e,
            );
        }
    }
}
