<?php

declare(strict_types=1);

namespace App\Service\CardIssuing;

use App\Service\OpenBankingException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Adyen Issuing — Visa & Mastercard debit cards.
 *
 * License:  De Nederlandsche Bank (DNB), Netherlands
 * Schemes:  Visa + Mastercard (principal member of both)
 * Coverage: 30+ EU/EEA countries
 * Powers:   eBay, Klarna, H&M, GoCardless, Zip
 *
 * Adyen combines acquiring + issuing in a single platform, which means
 * EU Pay can both issue cards AND process POS payments with one provider.
 *
 * NFC tokenization: Adyen supports push provisioning to Google Pay / Apple Pay
 * and raw DPAN issuance for custom HCE implementations.
 *
 * @see https://docs.adyen.com/issuing/
 */
class AdyenCardIssuer implements CardIssuerInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $baseUrl,        // https://balanceplatform-api-test.adyen.com/bcl/v2
        private readonly string $apiKey,          // X-API-Key
        private readonly string $balanceAccountId,
        private readonly string $balancePlatform,
        private readonly LoggerInterface $logger,
    ) {}

    public function createVirtualCard(string $userId, string $cardholderName, string $currency = 'EUR'): array
    {
        // Step 1: Create cardholder (account holder)
        $holder = $this->request('POST', '/accountHolders', [
            'balancePlatform' => $this->balancePlatform,
            'contactDetails' => ['fullPhoneNumber' => '+000000000'],
            'description' => "EU Pay user {$userId}",
            'reference' => $userId,
        ]);

        // Step 2: Create balance account
        $account = $this->request('POST', '/balanceAccounts', [
            'accountHolderId' => $holder['id'],
            'defaultCurrencyCode' => $currency,
            'description' => 'EU Pay card balance',
        ]);

        // Step 3: Create payment instrument (virtual card)
        $card = $this->request('POST', '/paymentInstruments', [
            'balanceAccountId' => $account['id'],
            'issuingCountryCode' => 'NL',
            'type' => 'card',
            'card' => [
                'brand' => 'visa',
                'brandVariant' => 'visadebit',
                'cardholderName' => $cardholderName,
                'formFactor' => 'virtual',
            ],
        ]);

        return [
            'cardId' => $card['id'],
            'status' => strtoupper($card['status'] ?? 'active'),
            'maskedPan' => $card['card']['lastFour'] ? ('************' . $card['card']['lastFour']) : '',
            'last4' => $card['card']['lastFour'] ?? '',
            'expiryMonth' => (int) ($card['card']['expiryMonth'] ?? 12),
            'expiryYear' => (int) ($card['card']['expiryYear'] ?? 2028),
            'scheme' => strtoupper($card['card']['brand'] ?? 'VISA'),
        ];
    }

    public function activateCard(string $cardId): array
    {
        $response = $this->request('PATCH', "/paymentInstruments/{$cardId}", [
            'status' => 'active',
        ]);
        return ['cardId' => $cardId, 'status' => 'ACTIVE'];
    }

    public function blockCard(string $cardId): array
    {
        $response = $this->request('PATCH', "/paymentInstruments/{$cardId}", [
            'status' => 'suspended',
            'statusReason' => 'suspectedFraud',
        ]);
        return ['cardId' => $cardId, 'status' => 'SUSPENDED'];
    }

    public function unblockCard(string $cardId): array
    {
        return $this->activateCard($cardId);
    }

    public function terminateCard(string $cardId): array
    {
        $this->request('PATCH', "/paymentInstruments/{$cardId}", [
            'status' => 'closed',
            'statusReason' => 'closedByCardholder',
        ]);
        return ['cardId' => $cardId, 'status' => 'TERMINATED'];
    }

    public function getCard(string $cardId): array
    {
        $response = $this->request('GET', "/paymentInstruments/{$cardId}");
        return [
            'cardId' => $response['id'],
            'status' => strtoupper($response['status'] ?? 'unknown'),
            'last4' => $response['card']['lastFour'] ?? '',
            'expiryMonth' => (int) ($response['card']['expiryMonth'] ?? 0),
            'expiryYear' => (int) ($response['card']['expiryYear'] ?? 0),
            'scheme' => strtoupper($response['card']['brand'] ?? 'VISA'),
        ];
    }

    public function provisionDigitalCard(string $cardId, string $deviceId, string $deviceFingerprint): array
    {
        // Adyen uses /paymentInstruments/{id}/networkTokens for DPAN provisioning
        $response = $this->request('POST', "/paymentInstruments/{$cardId}/networkTokens", [
            'device' => [
                'id' => $deviceId,
                'type' => 'mobilePhone',
                'fingerprint' => $deviceFingerprint,
            ],
            'type' => 'deviceToken',
        ]);

        return [
            'dpan' => $response['tokenData']['pan'] ?? '',
            'dpanExpiryMonth' => (int) ($response['tokenData']['expiryMonth'] ?? 12),
            'dpanExpiryYear' => (int) ($response['tokenData']['expiryYear'] ?? 2028),
            'tokenReferenceId' => $response['id'] ?? '',
            'tokenStatus' => strtoupper($response['status'] ?? 'ACTIVE'),
            'emvKeys' => [
                'iccPrivateKey' => $response['emvData']['iccPrivateKey'] ?? '',
                'iccCertificate' => $response['emvData']['iccCertificate'] ?? '',
                'issuerPublicKey' => $response['emvData']['issuerPublicKey'] ?? '',
            ],
        ];
    }

    public function generateEmvSessionKeys(string $tokenReferenceId, int $currentAtc): array
    {
        $response = $this->request('POST', "/networkTokens/{$tokenReferenceId}/emvSession", [
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
        $this->request('PATCH', "/networkTokens/{$tokenReferenceId}", [
            'status' => 'closed',
        ]);
        return ['tokenReferenceId' => $tokenReferenceId, 'status' => 'TERMINATED'];
    }

    public function getDigitalCardStatus(string $tokenReferenceId): array
    {
        $response = $this->request('GET', "/networkTokens/{$tokenReferenceId}");
        return [
            'tokenReferenceId' => $response['id'],
            'status' => strtoupper($response['status'] ?? 'UNKNOWN'),
        ];
    }

    public function loadFunds(string $cardId, int $amountCents, string $currency = 'EUR'): array
    {
        // Adyen uses balance platform transfers
        $card = $this->request('GET', "/paymentInstruments/{$cardId}");
        $response = $this->request('POST', '/transfers', [
            'amount' => ['value' => $amountCents, 'currency' => $currency],
            'balanceAccountId' => $card['balanceAccountId'],
            'category' => 'topUp',
            'description' => 'EU Pay top-up',
        ]);

        return [
            'transactionId' => $response['id'],
            'amount' => $amountCents / 100,
            'status' => strtoupper($response['status'] ?? 'COMPLETED'),
        ];
    }

    public function getCardBalance(string $cardId): array
    {
        $card = $this->request('GET', "/paymentInstruments/{$cardId}");
        $balance = $this->request('GET', "/balanceAccounts/{$card['balanceAccountId']}");

        $available = 0;
        foreach (($balance['balances'] ?? []) as $b) {
            if (($b['currency'] ?? '') === 'EUR') {
                $available = (int) ($b['available'] ?? 0);
            }
        }

        return ['available' => $available, 'ledger' => $available, 'currency' => 'EUR'];
    }

    private function request(string $method, string $path, array $body = []): array
    {
        $options = [
            'headers' => [
                'X-API-Key' => $this->apiKey,
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
            $this->logger->error('Adyen Issuing API error', [
                'method' => $method, 'path' => $path, 'error' => $e->getMessage(),
            ]);
            throw new OpenBankingException("Adyen Issuing error: {$e->getMessage()}", (int) $e->getCode(), $e);
        }
    }
}
