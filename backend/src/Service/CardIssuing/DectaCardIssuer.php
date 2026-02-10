<?php

declare(strict_types=1);

namespace App\Service\CardIssuing;

use App\Service\OpenBankingException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * DECTA — Full-stack payment processor and card issuer.
 *
 * License:  FCMC (Financial and Capital Market Commission), Latvia
 * Scheme:   Visa + Mastercard (principal member of both)
 * Coverage: EU/EEA via passporting from Latvia
 * Powers:   Multiple EU fintechs, white-label card programmes
 * Services: Card issuing, acquiring, processing, BIN sponsorship
 *
 * DECTA is a Latvian payment processor providing issuing + acquiring
 * on a single platform. REST API with real-time authorization webhooks.
 *
 * @see https://dapidocs.decta.com/Issuing-API/
 */
class DectaCardIssuer implements CardIssuerInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly string $programmeId,
        private readonly LoggerInterface $logger,
    ) {}

    public function createVirtualCard(string $userId, string $cardholderName, string $currency = 'EUR'): array
    {
        $cardholder = $this->request('POST', '/v1/cardholders', [
            'programme_id' => $this->programmeId,
            'full_name' => $cardholderName,
            'external_id' => $userId,
            'currency' => $currency,
        ]);

        $card = $this->request('POST', '/v1/cards', [
            'cardholder_id' => $cardholder['id'],
            'programme_id' => $this->programmeId,
            'type' => 'virtual',
            'currency' => $currency,
        ]);

        return [
            'cardId' => $card['id'] ?? '',
            'status' => strtoupper($card['status'] ?? 'ACTIVE'),
            'maskedPan' => $card['masked_pan'] ?? '',
            'last4' => $card['last4'] ?? '',
            'expiryMonth' => (int) ($card['expiry_month'] ?? 12),
            'expiryYear' => (int) ($card['expiry_year'] ?? 2028),
            'scheme' => strtoupper($card['scheme'] ?? 'VISA'),
        ];
    }

    public function activateCard(string $cardId): array
    {
        $this->request('POST', "/v1/cards/{$cardId}/activate");
        return ['cardId' => $cardId, 'status' => 'ACTIVE'];
    }

    public function blockCard(string $cardId): array
    {
        $this->request('POST', "/v1/cards/{$cardId}/block", ['reason' => 'user_request']);
        return ['cardId' => $cardId, 'status' => 'SUSPENDED'];
    }

    public function unblockCard(string $cardId): array
    {
        $this->request('POST', "/v1/cards/{$cardId}/unblock");
        return ['cardId' => $cardId, 'status' => 'ACTIVE'];
    }

    public function terminateCard(string $cardId): array
    {
        $this->request('POST', "/v1/cards/{$cardId}/terminate");
        return ['cardId' => $cardId, 'status' => 'TERMINATED'];
    }

    public function getCard(string $cardId): array
    {
        $card = $this->request('GET', "/v1/cards/{$cardId}");
        return [
            'cardId' => $card['id'] ?? '',
            'status' => strtoupper($card['status'] ?? 'UNKNOWN'),
            'last4' => $card['last4'] ?? '',
            'expiryMonth' => (int) ($card['expiry_month'] ?? 0),
            'expiryYear' => (int) ($card['expiry_year'] ?? 0),
            'scheme' => strtoupper($card['scheme'] ?? 'VISA'),
        ];
    }

    public function provisionDigitalCard(string $cardId, string $deviceId, string $deviceFingerprint): array
    {
        $response = $this->request('POST', "/v1/cards/{$cardId}/digitalize", [
            'device_id' => $deviceId,
            'device_fingerprint' => $deviceFingerprint,
            'token_type' => 'HCE',
        ]);

        return [
            'dpan' => $response['digital_card']['dpan'] ?? '',
            'dpanExpiryMonth' => (int) ($response['digital_card']['expiry_month'] ?? 12),
            'dpanExpiryYear' => (int) ($response['digital_card']['expiry_year'] ?? 2028),
            'tokenReferenceId' => $response['digital_card']['token_reference_id'] ?? '',
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
        $response = $this->request('POST', "/v1/digital-cards/{$tokenReferenceId}/emv-session", [
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
        $this->request('DELETE', "/v1/digital-cards/{$tokenReferenceId}");
        return ['tokenReferenceId' => $tokenReferenceId, 'status' => 'TERMINATED'];
    }

    public function getDigitalCardStatus(string $tokenReferenceId): array
    {
        $response = $this->request('GET', "/v1/digital-cards/{$tokenReferenceId}");
        return [
            'tokenReferenceId' => $tokenReferenceId,
            'status' => strtoupper($response['status'] ?? 'UNKNOWN'),
        ];
    }

    public function loadFunds(string $cardId, int $amountCents, string $currency = 'EUR'): array
    {
        $response = $this->request('POST', "/v1/cards/{$cardId}/load", [
            'amount' => $amountCents,
            'currency' => $currency,
        ]);

        return [
            'transactionId' => $response['transaction_id'] ?? '',
            'amount' => $amountCents / 100,
            'status' => strtoupper($response['status'] ?? 'COMPLETED'),
        ];
    }

    public function getCardBalance(string $cardId): array
    {
        $response = $this->request('GET', "/v1/cards/{$cardId}/balance");
        return [
            'available' => (int) ($response['available'] ?? 0),
            'ledger' => (int) ($response['ledger'] ?? 0),
            'currency' => $response['currency'] ?? 'EUR',
        ];
    }

    private function request(string $method, string $path, array $body = []): array
    {
        $options = [
            'headers' => [
                'Authorization' => "Bearer {$this->apiKey}",
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
            $this->logger->error('DECTA API error', [
                'method' => $method, 'path' => $path, 'error' => $e->getMessage(),
            ]);
            throw new OpenBankingException("DECTA error: {$e->getMessage()}", (int) $e->getCode(), $e);
        }
    }
}
