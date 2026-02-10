<?php

declare(strict_types=1);

namespace App\Service\CardIssuing;

use App\Service\OpenBankingException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Enfuce Card Issuing — Visa & Mastercard debit/prepaid/credit cards.
 *
 * License:  Finnish Financial Supervisory Authority (FIN-FSA), EMI licence
 * Schemes:  Visa + Mastercard (principal member of both)
 * Coverage: All 30 EEA countries (passported) + UK
 * Powers:   Porsche Card, SEB Embedded, Pleo, Finnish State Treasury/Kela
 * Founded:  2016, Helsinki — female-founded, €73.5M raised
 *
 * Enfuce is cloud-native (first PCI-DSS certified on public cloud),
 * supports Apple Pay / Google Pay push provisioning and custom HCE.
 * 22 million cardholders processed.
 *
 * @see https://docs.enfuce.com/
 */
class EnfuceCardIssuer implements CardIssuerInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $baseUrl,     // https://api.enfuce.com/v1
        private readonly string $apiKey,
        private readonly string $apiSecret,
        private readonly string $programId,   // card programme ID
        private readonly LoggerInterface $logger,
    ) {}

    public function createVirtualCard(string $userId, string $cardholderName, string $currency = 'EUR'): array
    {
        $nameParts = explode(' ', $cardholderName, 2);

        // Step 1: Create customer
        $customer = $this->request('POST', '/customers', [
            'externalId' => $userId,
            'firstName' => $nameParts[0],
            'lastName' => $nameParts[1] ?? '',
            'status' => 'ACTIVE',
        ]);

        // Step 2: Create account
        $account = $this->request('POST', '/accounts', [
            'customerId' => $customer['id'],
            'currency' => $currency,
            'type' => 'PREPAID',
            'programId' => $this->programId,
        ]);

        // Step 3: Create virtual card (Enfuce has separate endpoints for Visa/MC)
        $card = $this->request('POST', '/cards/visa/virtual', [
            'customerId' => $customer['id'],
            'accountId' => $account['id'],
            'firstName' => $nameParts[0],
            'lastName' => $nameParts[1] ?? '',
        ]);

        return [
            'cardId' => $card['id'],
            'status' => strtoupper($card['status'] ?? 'ACTIVE'),
            'maskedPan' => '************' . ($card['lastFour'] ?? ''),
            'last4' => $card['lastFour'] ?? '',
            'expiryMonth' => (int) ($card['expiryMonth'] ?? 12),
            'expiryYear' => (int) ($card['expiryYear'] ?? 2028),
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
            'reason' => 'CARDHOLDER_REQUEST',
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
        $this->request('POST', "/cards/{$cardId}/close", [
            'reason' => 'CARDHOLDER_REQUEST',
        ]);
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
        // Enfuce supports digital wallet provisioning for Google/Apple Pay and custom HCE
        $response = $this->request('POST', "/cards/{$cardId}/digitalWallets/provision", [
            'deviceId' => $deviceId,
            'deviceFingerprint' => $deviceFingerprint,
            'walletType' => 'CUSTOM_HCE',
        ]);

        return [
            'dpan' => $response['tokenPan'] ?? '',
            'dpanExpiryMonth' => (int) ($response['tokenExpiryMonth'] ?? 12),
            'dpanExpiryYear' => (int) ($response['tokenExpiryYear'] ?? 2028),
            'tokenReferenceId' => $response['tokenReferenceId'] ?? '',
            'tokenStatus' => strtoupper($response['tokenStatus'] ?? 'ACTIVE'),
            'emvKeys' => [
                'iccPrivateKey' => $response['emvKeys']['iccPrivateKey'] ?? '',
                'iccCertificate' => $response['emvKeys']['iccCertificate'] ?? '',
                'issuerPublicKey' => $response['emvKeys']['issuerPublicKey'] ?? '',
            ],
        ];
    }

    public function generateEmvSessionKeys(string $tokenReferenceId, int $currentAtc): array
    {
        $response = $this->request('POST', "/digitalWallets/{$tokenReferenceId}/emvSession", [
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
        $this->request('POST', "/digitalWallets/{$tokenReferenceId}/deactivate");
        return ['tokenReferenceId' => $tokenReferenceId, 'status' => 'TERMINATED'];
    }

    public function getDigitalCardStatus(string $tokenReferenceId): array
    {
        $response = $this->request('GET', "/digitalWallets/{$tokenReferenceId}");
        return [
            'tokenReferenceId' => $response['tokenReferenceId'] ?? $tokenReferenceId,
            'status' => strtoupper($response['tokenStatus'] ?? 'UNKNOWN'),
        ];
    }

    public function loadFunds(string $cardId, int $amountCents, string $currency = 'EUR'): array
    {
        $card = $this->request('GET', "/cards/{$cardId}");
        $response = $this->request('POST', "/accounts/{$card['accountId']}/load", [
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
        $card = $this->request('GET', "/cards/{$cardId}");
        $account = $this->request('GET', "/accounts/{$card['accountId']}");

        return [
            'available' => (int) ($account['availableBalance'] ?? 0),
            'ledger' => (int) ($account['ledgerBalance'] ?? 0),
            'currency' => $account['currency'] ?? 'EUR',
        ];
    }

    private function request(string $method, string $path, array $body = []): array
    {
        $options = [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($this->apiKey . ':' . $this->apiSecret),
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
            $this->logger->error('Enfuce API error', [
                'method' => $method, 'path' => $path, 'error' => $e->getMessage(),
            ]);
            throw new OpenBankingException("Enfuce API error: {$e->getMessage()}", (int) $e->getCode(), $e);
        }
    }
}
