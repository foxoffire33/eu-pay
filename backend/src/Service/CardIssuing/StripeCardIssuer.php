<?php

declare(strict_types=1);

namespace App\Service\CardIssuing;

use App\Service\OpenBankingException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Stripe Issuing — Visa debit cards for EU Pay.
 *
 * License:  Central Bank of Ireland (Stripe Technology Europe Ltd)
 * Schemes:  Visa
 * Coverage: 20 EU countries (AT, BE, CY, EE, FI, FR, DE, GR, IE, IT,
 *           LV, LT, LU, MT, NL, PT, SK, SI, ES + UK)
 * Powers:   Postmates, Ramp, Brex, Expensify
 *
 * Stripe Issuing integrates tightly with the Stripe payments ecosystem.
 * Real-time authorization webhooks let EU Pay approve/decline each tap.
 *
 * @see https://docs.stripe.com/issuing
 */
class StripeCardIssuer implements CardIssuerInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiKey,         // sk_live_... or sk_test_...
        private readonly string $baseUrl,        // https://api.stripe.com/v1
        private readonly LoggerInterface $logger,
    ) {}

    public function createVirtualCard(string $userId, string $cardholderName, string $currency = 'EUR'): array
    {
        // Step 1: Create cardholder
        $nameParts = explode(' ', $cardholderName, 2);
        $cardholder = $this->request('POST', '/issuing/cardholders', [
            'name' => $cardholderName,
            'type' => 'individual',
            'individual' => [
                'first_name' => $nameParts[0],
                'last_name' => $nameParts[1] ?? '',
            ],
            'billing' => [
                'address' => [
                    'line1' => 'Provided via app',
                    'city' => 'EU',
                    'country' => 'NL',
                    'postal_code' => '00000',
                ],
            ],
            'metadata' => ['eupay_user_id' => $userId],
        ]);

        // Step 2: Create virtual card
        $card = $this->request('POST', '/issuing/cards', [
            'cardholder' => $cardholder['id'],
            'currency' => strtolower($currency),
            'type' => 'virtual',
            'spending_controls' => [
                'spending_limits' => [
                    ['amount' => 500000, 'interval' => 'monthly'], // €5,000/month default
                ],
            ],
            'metadata' => ['eupay_user_id' => $userId],
        ]);

        return [
            'cardId' => $card['id'],
            'status' => strtoupper($card['status'] ?? 'active'),
            'maskedPan' => '************' . ($card['last4'] ?? ''),
            'last4' => $card['last4'] ?? '',
            'expiryMonth' => (int) ($card['exp_month'] ?? 12),
            'expiryYear' => (int) ($card['exp_year'] ?? 2028),
            'scheme' => 'VISA',
        ];
    }

    public function activateCard(string $cardId): array
    {
        $response = $this->request('POST', "/issuing/cards/{$cardId}", [
            'status' => 'active',
        ]);
        return ['cardId' => $cardId, 'status' => 'ACTIVE'];
    }

    public function blockCard(string $cardId): array
    {
        $response = $this->request('POST', "/issuing/cards/{$cardId}", [
            'status' => 'inactive',
            'cancellation_reason' => 'lost',
        ]);
        return ['cardId' => $cardId, 'status' => 'SUSPENDED'];
    }

    public function unblockCard(string $cardId): array
    {
        return $this->activateCard($cardId);
    }

    public function terminateCard(string $cardId): array
    {
        $this->request('POST', "/issuing/cards/{$cardId}", [
            'status' => 'canceled',
        ]);
        return ['cardId' => $cardId, 'status' => 'TERMINATED'];
    }

    public function getCard(string $cardId): array
    {
        $response = $this->request('GET', "/issuing/cards/{$cardId}");
        $statusMap = ['active' => 'ACTIVE', 'inactive' => 'SUSPENDED', 'canceled' => 'TERMINATED'];
        return [
            'cardId' => $response['id'],
            'status' => $statusMap[$response['status']] ?? strtoupper($response['status']),
            'last4' => $response['last4'] ?? '',
            'expiryMonth' => (int) ($response['exp_month'] ?? 0),
            'expiryYear' => (int) ($response['exp_year'] ?? 0),
            'scheme' => 'VISA',
        ];
    }

    public function provisionDigitalCard(string $cardId, string $deviceId, string $deviceFingerprint): array
    {
        // Stripe uses /issuing/cards/{id}/digital_wallets for tokenization
        $response = $this->request('POST', "/issuing/cards/{$cardId}/digital_wallets", [
            'device' => [
                'device_id' => $deviceId,
                'device_fingerprint' => $deviceFingerprint,
                'type' => 'phone',
            ],
            'wallet_provider' => 'custom_hce',
        ]);

        return [
            'dpan' => $response['token_data']['pan'] ?? '',
            'dpanExpiryMonth' => (int) ($response['token_data']['exp_month'] ?? 12),
            'dpanExpiryYear' => (int) ($response['token_data']['exp_year'] ?? 2028),
            'tokenReferenceId' => $response['id'] ?? '',
            'tokenStatus' => 'ACTIVE',
            'emvKeys' => [
                'iccPrivateKey' => $response['emv_keys']['icc_private_key'] ?? '',
                'iccCertificate' => $response['emv_keys']['icc_certificate'] ?? '',
                'issuerPublicKey' => $response['emv_keys']['issuer_public_key'] ?? '',
            ],
        ];
    }

    public function generateEmvSessionKeys(string $tokenReferenceId, int $currentAtc): array
    {
        $response = $this->request('POST', "/issuing/digital_wallets/{$tokenReferenceId}/emv_session", [
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
        $this->request('POST', "/issuing/digital_wallets/{$tokenReferenceId}/deactivate");
        return ['tokenReferenceId' => $tokenReferenceId, 'status' => 'TERMINATED'];
    }

    public function getDigitalCardStatus(string $tokenReferenceId): array
    {
        $response = $this->request('GET', "/issuing/digital_wallets/{$tokenReferenceId}");
        return [
            'tokenReferenceId' => $response['id'],
            'status' => strtoupper($response['status'] ?? 'UNKNOWN'),
        ];
    }

    public function loadFunds(string $cardId, int $amountCents, string $currency = 'EUR'): array
    {
        // Stripe Issuing uses top-ups to the Issuing balance
        $response = $this->request('POST', '/topups', [
            'amount' => $amountCents,
            'currency' => strtolower($currency),
            'description' => 'EU Pay top-up',
            'metadata' => ['card_id' => $cardId],
        ]);

        return [
            'transactionId' => $response['id'],
            'amount' => $amountCents / 100,
            'status' => strtoupper($response['status'] ?? 'SUCCEEDED'),
        ];
    }

    public function getCardBalance(string $cardId): array
    {
        $response = $this->request('GET', '/issuing/balance');
        $available = 0;
        foreach (($response['available'] ?? []) as $b) {
            if (($b['currency'] ?? '') === 'eur') {
                $available = (int) ($b['amount'] ?? 0);
            }
        }
        return ['available' => $available, 'ledger' => $available, 'currency' => 'EUR'];
    }

    private function request(string $method, string $path, array $body = []): array
    {
        $options = [
            'auth_bearer' => $this->apiKey,
            'headers' => ['Stripe-Version' => '2024-12-18'],
        ];

        if (!empty($body) && $method !== 'GET') {
            $options['body'] = $this->flattenParams($body);
        }

        try {
            $response = $this->httpClient->request($method, $this->baseUrl . $path, $options);
            return $response->toArray();
        } catch (\Throwable $e) {
            $this->logger->error('Stripe Issuing API error', [
                'method' => $method, 'path' => $path, 'error' => $e->getMessage(),
            ]);
            throw new OpenBankingException("Stripe Issuing error: {$e->getMessage()}", (int) $e->getCode(), $e);
        }
    }

    /** Stripe API uses form-encoded nested params: card[brand] = visa */
    private function flattenParams(array $params, string $prefix = ''): array
    {
        $result = [];
        foreach ($params as $key => $value) {
            $fullKey = $prefix ? "{$prefix}[{$key}]" : $key;
            if (is_array($value)) {
                $result = array_merge($result, $this->flattenParams($value, $fullKey));
            } else {
                $result[$fullKey] = (string) $value;
            }
        }
        return $result;
    }
}
