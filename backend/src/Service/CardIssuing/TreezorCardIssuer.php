<?php

declare(strict_types=1);

namespace App\Service\CardIssuing;

use App\Service\OpenBankingException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Treezor — Banking-as-a-Service by Société Générale.
 *
 * License:  ACPR (Autorité de Contrôle Prudentiel et de Résolution), France
 * Scheme:   Mastercard (principal member)
 * Coverage: 25 EU/EEA countries (passported)
 * Branches: France, Germany, Belgium, Italy, Spain
 * Powers:   Qonto, Lydia, Swile, Shine, Pixpay, Bling
 * Stats:    7M+ cards issued, €120B+ flows processed
 * Parent:   Société Générale Group (acquired 2019)
 *
 * Treezor is Europe's leading BaaS provider. Its one-stop-shop embedded
 * finance solution covers issuance (physical + virtual), SEPA, KYC,
 * and e-money — all via a single REST API.
 *
 * @see https://www.treezor.com/documentation/
 */
class TreezorCardIssuer implements CardIssuerInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $baseUrl,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $walletTypeId,
        private readonly LoggerInterface $logger,
    ) {}

    public function createVirtualCard(string $userId, string $cardholderName, string $currency = 'EUR'): array
    {
        // Step 1: Create user
        $nameParts = explode(' ', $cardholderName, 2);
        $user = $this->request('POST', '/v1/users', [
            'userTypeId' => 1,
            'userFirstname' => $nameParts[0],
            'userLastname' => $nameParts[1] ?? $nameParts[0],
            'email' => "{$userId}@eupay.eu",
            'currency' => $currency,
            'userTag' => $userId,
        ]);

        $treezorUserId = $user['users'][0]['userId'] ?? '';

        // Step 2: Create wallet
        $wallet = $this->request('POST', '/v1/wallets', [
            'userId' => $treezorUserId,
            'walletTypeId' => $this->walletTypeId,
            'currency' => $currency,
            'eventName' => 'EU Pay wallet',
            'walletTag' => $userId,
        ]);

        $walletId = $wallet['wallets'][0]['walletId'] ?? '';

        // Step 3: Create virtual card
        $card = $this->request('POST', '/v1/cards', [
            'userId' => $treezorUserId,
            'walletId' => $walletId,
            'cardPrint' => 'VIRTUAL',
            'currency' => $currency,
        ]);

        $c = $card['cards'][0] ?? [];
        return [
            'cardId' => (string) ($c['cardId'] ?? ''),
            'status' => strtoupper($c['statusCode'] ?? 'ACTIVE'),
            'maskedPan' => $c['maskedPan'] ?? '',
            'last4' => substr($c['maskedPan'] ?? '', -4),
            'expiryMonth' => (int) ($c['expiryDateMonth'] ?? 12),
            'expiryYear' => (int) ($c['expiryDateYear'] ?? 2028),
            'scheme' => 'MASTERCARD',
        ];
    }

    public function activateCard(string $cardId): array
    {
        $this->request('PUT', "/v1/cards/{$cardId}", ['statusCode' => 'ACTIVE']);
        return ['cardId' => $cardId, 'status' => 'ACTIVE'];
    }

    public function blockCard(string $cardId): array
    {
        $this->request('PUT', "/v1/cards/{$cardId}", ['statusCode' => 'BLOCKED']);
        return ['cardId' => $cardId, 'status' => 'SUSPENDED'];
    }

    public function unblockCard(string $cardId): array
    {
        return $this->activateCard($cardId);
    }

    public function terminateCard(string $cardId): array
    {
        $this->request('PUT', "/v1/cards/{$cardId}", ['statusCode' => 'CANCELLED']);
        return ['cardId' => $cardId, 'status' => 'TERMINATED'];
    }

    public function getCard(string $cardId): array
    {
        $response = $this->request('GET', "/v1/cards/{$cardId}");
        $c = $response['cards'][0] ?? [];
        return [
            'cardId' => (string) ($c['cardId'] ?? ''),
            'status' => strtoupper($c['statusCode'] ?? 'UNKNOWN'),
            'maskedPan' => $c['maskedPan'] ?? '',
            'last4' => substr($c['maskedPan'] ?? '', -4),
            'expiryMonth' => (int) ($c['expiryDateMonth'] ?? 0),
            'expiryYear' => (int) ($c['expiryDateYear'] ?? 0),
            'scheme' => 'MASTERCARD',
        ];
    }

    public function provisionDigitalCard(string $cardId, string $deviceId, string $deviceFingerprint): array
    {
        $response = $this->request('POST', "/v1/cards/{$cardId}/DigitalizeCard", [
            'deviceId' => $deviceId,
            'deviceFingerprint' => $deviceFingerprint,
            'walletProvider' => 'HCE',
        ]);

        return [
            'dpan' => $response['digitalCard']['dpan'] ?? '',
            'dpanExpiryMonth' => (int) ($response['digitalCard']['expiryMonth'] ?? 12),
            'dpanExpiryYear' => (int) ($response['digitalCard']['expiryYear'] ?? 2028),
            'tokenReferenceId' => $response['digitalCard']['tokenReferenceId'] ?? '',
            'tokenStatus' => 'ACTIVE',
            'emvKeys' => [
                'iccPrivateKey' => $response['emvKeys']['iccPrivateKey'] ?? '',
                'iccCertificate' => $response['emvKeys']['iccCertificate'] ?? '',
                'issuerPublicKey' => $response['emvKeys']['issuerPublicKey'] ?? '',
            ],
        ];
    }

    public function generateEmvSessionKeys(string $tokenReferenceId, int $currentAtc): array
    {
        $response = $this->request('POST', "/v1/digitalCards/{$tokenReferenceId}/emvSession", [
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
        $this->request('DELETE', "/v1/digitalCards/{$tokenReferenceId}");
        return ['tokenReferenceId' => $tokenReferenceId, 'status' => 'TERMINATED'];
    }

    public function getDigitalCardStatus(string $tokenReferenceId): array
    {
        $response = $this->request('GET', "/v1/digitalCards/{$tokenReferenceId}");
        return [
            'tokenReferenceId' => $tokenReferenceId,
            'status' => strtoupper($response['digitalCard']['status'] ?? 'UNKNOWN'),
        ];
    }

    public function loadFunds(string $cardId, int $amountCents, string $currency = 'EUR'): array
    {
        $card = $this->request('GET', "/v1/cards/{$cardId}");
        $walletId = $card['cards'][0]['walletId'] ?? '';

        $response = $this->request('POST', '/v1/payins', [
            'walletId' => $walletId,
            'amount' => number_format($amountCents / 100, 2, '.', ''),
            'currency' => $currency,
            'paymentMethodId' => 26, // internal transfer
        ]);

        return [
            'transactionId' => (string) ($response['payins'][0]['payinId'] ?? ''),
            'amount' => $amountCents / 100,
            'status' => 'COMPLETED',
        ];
    }

    public function getCardBalance(string $cardId): array
    {
        $card = $this->request('GET', "/v1/cards/{$cardId}");
        $walletId = $card['cards'][0]['walletId'] ?? '';

        $wallet = $this->request('GET', "/v1/wallets/{$walletId}");
        $w = $wallet['wallets'][0] ?? [];

        return [
            'available' => (int) (($w['solde'] ?? 0) * 100),
            'ledger' => (int) (($w['authorizedBalance'] ?? 0) * 100),
            'currency' => $w['currency'] ?? 'EUR',
        ];
    }

    private function request(string $method, string $path, array $body = []): array
    {
        $options = [
            'headers' => [
                'Authorization' => "Bearer {$this->clientSecret}",
                'Content-Type' => 'application/json',
                'X-Client-Id' => $this->clientId,
            ],
        ];

        if (!empty($body) && $method !== 'GET') {
            $options['json'] = $body;
        }

        try {
            $response = $this->httpClient->request($method, $this->baseUrl . $path, $options);
            return $response->toArray();
        } catch (\Throwable $e) {
            $this->logger->error('Treezor API error', [
                'method' => $method, 'path' => $path, 'error' => $e->getMessage(),
            ]);
            throw new OpenBankingException("Treezor error: {$e->getMessage()}", (int) $e->getCode(), $e);
        }
    }
}
