<?php

declare(strict_types=1);

namespace App\Service\CardIssuing;

use App\Service\OpenBankingException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Wallester Card Issuing — Visa debit/prepaid/credit cards.
 *
 * License:  Estonian Financial Supervision Authority (EFSA)
 * Schemes:  Visa (principal member since 2018, FinTech Fast Track)
 * Coverage: 30 EEA countries + UK (cross-border licence)
 * HQ:       Tallinn, Estonia — founded 2016
 *
 * Wallester is especially attractive for startups (free tier available,
 * up to 300 virtual cards at €0/month). REST API with instant virtual
 * card issuance. Supports Apple Pay, Google Pay, Samsung Pay.
 *
 * Uses Visa Token Service for secure tokenization.
 *
 * @see https://wallester.com/api/card-issuing
 */
class WallesterCardIssuer implements CardIssuerInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $baseUrl,     // https://api.wallester.com/v1
        private readonly string $apiKey,
        private readonly string $apiSecret,
        private readonly string $productId,   // card product ID
        private readonly LoggerInterface $logger,
    ) {}

    public function createVirtualCard(string $userId, string $cardholderName, string $currency = 'EUR'): array
    {
        $nameParts = explode(' ', $cardholderName, 2);

        // Step 1: Create person (cardholder)
        $person = $this->request('POST', '/persons', [
            'external_id' => $userId,
            'first_name' => $nameParts[0],
            'last_name' => $nameParts[1] ?? '',
            'status' => 'Active',
        ]);

        // Step 2: Create account
        $account = $this->request('POST', '/accounts', [
            'person_id' => $person['id'],
            'product_id' => $this->productId,
            'currency' => $currency,
        ]);

        // Step 3: Create virtual card
        $card = $this->request('POST', '/cards', [
            'account_id' => $account['id'],
            'person_id' => $person['id'],
            'type' => 'Virtual',
            'cardholder_first_name' => $nameParts[0],
            'cardholder_last_name' => $nameParts[1] ?? '',
        ]);

        return [
            'cardId' => $card['id'],
            'status' => strtoupper($card['status'] ?? 'ACTIVE'),
            'maskedPan' => '************' . ($card['card_mask'] ?? ''),
            'last4' => $card['card_mask'] ?? '',
            'expiryMonth' => (int) ($card['expiry_month'] ?? 12),
            'expiryYear' => (int) ($card['expiry_year'] ?? 2028),
            'scheme' => 'VISA',
        ];
    }

    public function activateCard(string $cardId): array
    {
        $this->request('POST', "/cards/{$cardId}/activate");
        return ['cardId' => $cardId, 'status' => 'ACTIVE'];
    }

    public function blockCard(string $cardId): array
    {
        $this->request('POST', "/cards/{$cardId}/block", [
            'reason' => 'CardholderRequest',
        ]);
        return ['cardId' => $cardId, 'status' => 'SUSPENDED'];
    }

    public function unblockCard(string $cardId): array
    {
        $this->request('POST', "/cards/{$cardId}/unblock");
        return ['cardId' => $cardId, 'status' => 'ACTIVE'];
    }

    public function terminateCard(string $cardId): array
    {
        $this->request('POST', "/cards/{$cardId}/close");
        return ['cardId' => $cardId, 'status' => 'TERMINATED'];
    }

    public function getCard(string $cardId): array
    {
        $response = $this->request('GET', "/cards/{$cardId}");
        return [
            'cardId' => $response['id'],
            'status' => strtoupper($response['status'] ?? 'UNKNOWN'),
            'last4' => $response['card_mask'] ?? '',
            'expiryMonth' => (int) ($response['expiry_month'] ?? 0),
            'expiryYear' => (int) ($response['expiry_year'] ?? 0),
            'scheme' => 'VISA',
        ];
    }

    public function provisionDigitalCard(string $cardId, string $deviceId, string $deviceFingerprint): array
    {
        // Wallester uses Visa Token Service for tokenization
        $response = $this->request('POST', "/cards/{$cardId}/digital_wallets", [
            'device_id' => $deviceId,
            'device_fingerprint' => $deviceFingerprint,
            'wallet_type' => 'custom_hce',
            'token_service' => 'visa_token_service',
        ]);

        return [
            'dpan' => $response['token_pan'] ?? '',
            'dpanExpiryMonth' => (int) ($response['token_expiry_month'] ?? 12),
            'dpanExpiryYear' => (int) ($response['token_expiry_year'] ?? 2028),
            'tokenReferenceId' => $response['token_reference_id'] ?? '',
            'tokenStatus' => strtoupper($response['token_status'] ?? 'ACTIVE'),
            'emvKeys' => [
                'iccPrivateKey' => $response['emv_keys']['icc_private_key'] ?? '',
                'iccCertificate' => $response['emv_keys']['icc_certificate'] ?? '',
                'issuerPublicKey' => $response['emv_keys']['issuer_public_key'] ?? '',
            ],
        ];
    }

    public function generateEmvSessionKeys(string $tokenReferenceId, int $currentAtc): array
    {
        $response = $this->request('POST', "/digital_wallets/{$tokenReferenceId}/emv_session", [
            'atc' => $currentAtc + 1,
        ]);

        return [
            'sessionKey' => $response['session_key'] ?? '',
            'arqc' => $response['arqc'] ?? '',
            'atc' => (int) ($response['atc'] ?? $currentAtc + 1),
            'unpredictableNumber' => $response['unpredictable_number'] ?? bin2hex(random_bytes(4)),
        ];
    }

    public function deactivateDigitalCard(string $tokenReferenceId): array
    {
        $this->request('POST', "/digital_wallets/{$tokenReferenceId}/deactivate");
        return ['tokenReferenceId' => $tokenReferenceId, 'status' => 'TERMINATED'];
    }

    public function getDigitalCardStatus(string $tokenReferenceId): array
    {
        $response = $this->request('GET', "/digital_wallets/{$tokenReferenceId}");
        return [
            'tokenReferenceId' => $response['token_reference_id'] ?? $tokenReferenceId,
            'status' => strtoupper($response['token_status'] ?? 'UNKNOWN'),
        ];
    }

    public function loadFunds(string $cardId, int $amountCents, string $currency = 'EUR'): array
    {
        $card = $this->request('GET', "/cards/{$cardId}");
        $response = $this->request('POST', "/accounts/{$card['account_id']}/top_up", [
            'amount' => $amountCents,
            'currency' => $currency,
            'description' => 'EU Pay top-up',
        ]);

        return [
            'transactionId' => $response['transaction_id'] ?? '',
            'amount' => $amountCents / 100,
            'status' => strtoupper($response['status'] ?? 'COMPLETED'),
        ];
    }

    public function getCardBalance(string $cardId): array
    {
        $card = $this->request('GET', "/cards/{$cardId}");
        $account = $this->request('GET', "/accounts/{$card['account_id']}");

        return [
            'available' => (int) ($account['available_amount'] ?? 0),
            'ledger' => (int) ($account['ledger_amount'] ?? 0),
            'currency' => $account['currency'] ?? 'EUR',
        ];
    }

    private function request(string $method, string $path, array $body = []): array
    {
        $options = [
            'headers' => [
                'X-Api-Key' => $this->apiKey,
                'X-Api-Secret' => $this->apiSecret,
                'Content-Type' => 'application/json',
            ],
        ];

        if (!empty($body) && $method !== 'GET') {
            $options['json'] = $body;
        }

        try {
            $response = $this->httpClient->request($method, $this->baseUrl . $path, $options);
            return $response->toArray();
        } catch (\Throwable $e) {
            $this->logger->error('Wallester API error', [
                'method' => $method, 'path' => $path, 'error' => $e->getMessage(),
            ]);
            throw new OpenBankingException("Wallester API error: {$e->getMessage()}", (int) $e->getCode(), $e);
        }
    }
}
