<?php

declare(strict_types=1);

namespace App\Service\CardIssuing;

use App\Service\OpenBankingException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Nexi Group Card Issuing — Visa & Mastercard cards.
 *
 * License:  Banca d'Italia (after merger with SIA + Nets)
 * Schemes:  Visa + Mastercard
 * Coverage: EU-wide (IT, DE, DK, NO, SE, FI, PL, AT, CH, GR, HR, CZ +)
 * HQ:       Milan, Italy — €3.6B revenue, 7,000+ employees
 * Powers:   2.9 billion transactions/year, 1,000+ bank partners
 *
 * Nexi is the largest European paytech company by transaction volume.
 * Formed by the merger of Nexi (IT) + SIA (IT) + Nets (DK/NO/SE/FI).
 * Ideal for EU Pay due to deep European banking relationships and
 * native EU data residency. Supports contactless, HCE, and wallets.
 *
 * @see https://developer.nexigroup.com/
 */
class NexiCardIssuer implements CardIssuerInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $baseUrl,       // https://api.nexigroup.com/issuing/v1
        private readonly string $apiKey,
        private readonly string $merchantId,
        private readonly string $programmeToken,
        private readonly LoggerInterface $logger,
    ) {}

    public function createVirtualCard(string $userId, string $cardholderName, string $currency = 'EUR'): array
    {
        $nameParts = explode(' ', $cardholderName, 2);

        $cardholder = $this->request('POST', '/cardholders', [
            'externalReference' => $userId,
            'firstName' => $nameParts[0],
            'lastName' => $nameParts[1] ?? '',
            'programmeToken' => $this->programmeToken,
        ]);

        $card = $this->request('POST', '/cards', [
            'cardholderToken' => $cardholder['token'],
            'type' => 'VIRTUAL',
            'currency' => $currency,
            'scheme' => 'VISA',
            'fulfillment' => ['cardPersonalization' => ['nameOnCard' => $cardholderName]],
        ]);

        return [
            'cardId' => $card['token'],
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
        $this->request('POST', "/cards/{$cardId}/transitions", [
            'state' => 'ACTIVE',
            'reason' => 'Cardholder activation',
        ]);
        return ['cardId' => $cardId, 'status' => 'ACTIVE'];
    }

    public function blockCard(string $cardId): array
    {
        $this->request('POST', "/cards/{$cardId}/transitions", [
            'state' => 'SUSPENDED',
            'reason' => 'Cardholder request',
        ]);
        return ['cardId' => $cardId, 'status' => 'SUSPENDED'];
    }

    public function unblockCard(string $cardId): array
    {
        return $this->activateCard($cardId);
    }

    public function terminateCard(string $cardId): array
    {
        $this->request('POST', "/cards/{$cardId}/transitions", [
            'state' => 'TERMINATED',
            'reason' => 'Cardholder request',
        ]);
        return ['cardId' => $cardId, 'status' => 'TERMINATED'];
    }

    public function getCard(string $cardId): array
    {
        $response = $this->request('GET', "/cards/{$cardId}");
        return [
            'cardId' => $response['token'],
            'status' => strtoupper($response['status'] ?? 'UNKNOWN'),
            'last4' => $response['lastFour'] ?? '',
            'expiryMonth' => (int) ($response['expiryMonth'] ?? 0),
            'expiryYear' => (int) ($response['expiryYear'] ?? 0),
            'scheme' => strtoupper($response['scheme'] ?? 'VISA'),
        ];
    }

    public function provisionDigitalCard(string $cardId, string $deviceId, string $deviceFingerprint): array
    {
        $response = $this->request('POST', "/cards/{$cardId}/digitalwallets/provision", [
            'deviceId' => $deviceId,
            'deviceFingerprint' => $deviceFingerprint,
            'walletType' => 'HCE',
            'tokenServiceProvider' => 'VISA_TOKEN_SERVICE',
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
        $response = $this->request('POST', "/digitalwallets/{$tokenReferenceId}/emvsession", [
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
        $this->request('POST', "/digitalwallets/{$tokenReferenceId}/deactivate");
        return ['tokenReferenceId' => $tokenReferenceId, 'status' => 'TERMINATED'];
    }

    public function getDigitalCardStatus(string $tokenReferenceId): array
    {
        $response = $this->request('GET', "/digitalwallets/{$tokenReferenceId}");
        return [
            'tokenReferenceId' => $response['tokenReferenceId'] ?? $tokenReferenceId,
            'status' => strtoupper($response['tokenStatus'] ?? 'UNKNOWN'),
        ];
    }

    public function loadFunds(string $cardId, int $amountCents, string $currency = 'EUR'): array
    {
        $response = $this->request('POST', "/cards/{$cardId}/funding", [
            'amount' => $amountCents,
            'currency' => $currency,
            'type' => 'TOP_UP',
            'description' => 'EU Pay top-up',
        ]);

        return [
            'transactionId' => $response['transactionToken'] ?? '',
            'amount' => $amountCents / 100,
            'status' => strtoupper($response['status'] ?? 'COMPLETED'),
        ];
    }

    public function getCardBalance(string $cardId): array
    {
        $response = $this->request('GET', "/cards/{$cardId}/balance");
        return [
            'available' => (int) ($response['availableBalance'] ?? 0),
            'ledger' => (int) ($response['ledgerBalance'] ?? 0),
            'currency' => $response['currency'] ?? 'EUR',
        ];
    }

    private function request(string $method, string $path, array $body = []): array
    {
        $options = [
            'headers' => [
                'Authorization' => "Bearer {$this->apiKey}",
                'X-Merchant-Id' => $this->merchantId,
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
            $this->logger->error('Nexi API error', [
                'method' => $method, 'path' => $path, 'error' => $e->getMessage(),
            ]);
            throw new OpenBankingException("Nexi API error: {$e->getMessage()}", (int) $e->getCode(), $e);
        }
    }
}
