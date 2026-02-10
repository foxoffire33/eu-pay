<?php

declare(strict_types=1);

namespace App\Service\CardIssuing;

use App\Service\OpenBankingException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Paynetics Card Issuing — Visa & Mastercard cards.
 *
 * License:  Bulgarian National Bank (BNB), EMI licence (2015)
 * Schemes:  Visa + Mastercard (principal member of both)
 * Coverage: All EEA countries (passported)
 * HQ:       Sofia, Bulgaria — founded 2015
 * Powers:   phyre, iCard, Phos (tap-to-phone)
 *
 * Paynetics is one of the fastest-growing Eastern European EMIs.
 * Offers competitive pricing for CEE markets. Supports physical,
 * virtual, and tokenized cards with real-time authorization.
 *
 * @see https://paynetics.digital/
 */
class PayneticsCardIssuer implements CardIssuerInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly string $clientId,
        private readonly string $programmeId,
        private readonly LoggerInterface $logger,
    ) {}

    public function createVirtualCard(string $userId, string $cardholderName, string $currency = 'EUR'): array
    {
        $nameParts = explode(' ', $cardholderName, 2);

        $cardholder = $this->request('POST', '/cardholders', [
            'externalId' => $userId,
            'firstName' => $nameParts[0],
            'lastName' => $nameParts[1] ?? '',
            'programmeId' => $this->programmeId,
        ]);

        $card = $this->request('POST', '/cards', [
            'cardholderId' => $cardholder['id'],
            'type' => 'VIRTUAL',
            'currency' => $currency,
            'scheme' => 'VISA',
        ]);

        return [
            'cardId' => $card['id'],
            'status' => strtoupper($card['status'] ?? 'ACTIVE'),
            'maskedPan' => '************' . ($card['lastFour'] ?? ''),
            'last4' => $card['lastFour'] ?? '',
            'expiryMonth' => (int) ($card['expiryMonth'] ?? 12),
            'expiryYear' => (int) ($card['expiryYear'] ?? 2028),
            'scheme' => strtoupper($card['scheme'] ?? 'VISA'),
        ];
    }

    public function activateCard(string $cardId): array
    {
        $this->request('POST', "/cards/{$cardId}/activate");
        return ['cardId' => $cardId, 'status' => 'ACTIVE'];
    }

    public function blockCard(string $cardId): array
    {
        $this->request('POST', "/cards/{$cardId}/suspend");
        return ['cardId' => $cardId, 'status' => 'SUSPENDED'];
    }

    public function unblockCard(string $cardId): array
    {
        $this->request('POST', "/cards/{$cardId}/unsuspend");
        return ['cardId' => $cardId, 'status' => 'ACTIVE'];
    }

    public function terminateCard(string $cardId): array
    {
        $this->request('POST', "/cards/{$cardId}/terminate");
        return ['cardId' => $cardId, 'status' => 'TERMINATED'];
    }

    public function getCard(string $cardId): array
    {
        $response = $this->request('GET', "/cards/{$cardId}");
        return [
            'cardId' => $response['id'],
            'status' => strtoupper($response['status'] ?? 'UNKNOWN'),
            'last4' => $response['lastFour'] ?? '',
            'expiryMonth' => (int) ($response['expiryMonth'] ?? 0),
            'expiryYear' => (int) ($response['expiryYear'] ?? 0),
            'scheme' => strtoupper($response['scheme'] ?? 'VISA'),
        ];
    }

    public function provisionDigitalCard(string $cardId, string $deviceId, string $deviceFingerprint): array
    {
        $response = $this->request('POST', "/cards/{$cardId}/tokenize", [
            'deviceId' => $deviceId,
            'deviceFingerprint' => $deviceFingerprint,
            'tokenType' => 'HCE',
        ]);

        return [
            'dpan' => $response['tokenPan'] ?? '',
            'dpanExpiryMonth' => (int) ($response['tokenExpiryMonth'] ?? 12),
            'dpanExpiryYear' => (int) ($response['tokenExpiryYear'] ?? 2028),
            'tokenReferenceId' => $response['tokenReferenceId'] ?? '',
            'tokenStatus' => strtoupper($response['status'] ?? 'ACTIVE'),
            'emvKeys' => [
                'iccPrivateKey' => $response['emvKeys']['iccPrivateKey'] ?? '',
                'iccCertificate' => $response['emvKeys']['iccCertificate'] ?? '',
                'issuerPublicKey' => $response['emvKeys']['issuerPublicKey'] ?? '',
            ],
        ];
    }

    public function generateEmvSessionKeys(string $tokenReferenceId, int $currentAtc): array
    {
        $response = $this->request('POST', "/tokens/{$tokenReferenceId}/session", [
            'atc' => $currentAtc + 1,
        ]);

        return [
            'sessionKey' => $response['sessionKey'] ?? '',
            'arqc' => $response['arqc'] ?? '',
            'atc' => (int) ($response['atc'] ?? $currentAtc + 1),
            'unpredictableNumber' => $response['unpredictableNumber'] ?? bin2hex(random_bytes(4)),
        ];
    }

    public function deactivateDigitalCard(string $tokenReferenceId): array
    {
        $this->request('POST', "/tokens/{$tokenReferenceId}/deactivate");
        return ['tokenReferenceId' => $tokenReferenceId, 'status' => 'TERMINATED'];
    }

    public function getDigitalCardStatus(string $tokenReferenceId): array
    {
        $response = $this->request('GET', "/tokens/{$tokenReferenceId}");
        return [
            'tokenReferenceId' => $response['tokenReferenceId'] ?? $tokenReferenceId,
            'status' => strtoupper($response['status'] ?? 'UNKNOWN'),
        ];
    }

    public function loadFunds(string $cardId, int $amountCents, string $currency = 'EUR'): array
    {
        $response = $this->request('POST', "/cards/{$cardId}/load", [
            'amount' => $amountCents,
            'currency' => $currency,
            'reference' => 'EU Pay top-up',
        ]);

        return [
            'transactionId' => $response['transactionId'] ?? '',
            'amount' => $amountCents / 100,
            'status' => strtoupper($response['status'] ?? 'COMPLETED'),
        ];
    }

    public function getCardBalance(string $cardId): array
    {
        $response = $this->request('GET', "/cards/{$cardId}/balance");
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
                'X-Client-Id' => $this->clientId,
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
            $this->logger->error('Paynetics API error', [
                'method' => $method, 'path' => $path, 'error' => $e->getMessage(),
            ]);
            throw new OpenBankingException("Paynetics API error: {$e->getMessage()}", (int) $e->getCode(), $e);
        }
    }
}
