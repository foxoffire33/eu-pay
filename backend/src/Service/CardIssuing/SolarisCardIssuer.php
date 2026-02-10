<?php

declare(strict_types=1);

namespace App\Service\CardIssuing;

use App\Service\OpenBankingException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Solaris SE — Europe's largest embedded finance platform.
 *
 * License:  BaFin (German Federal Financial Supervisory Authority), full CRR banking licence
 * Scheme:   Visa + Mastercard
 * Coverage: All EU/EEA countries via passporting + branches in FR, IT, ES
 * Powers:   Samsung Pay DE, ADAC, Trade Republic (formerly), Vivid Money
 * Stats:    €137M net revenue (2023), 700+ employees, SBI Group majority shareholder
 *
 * Solaris holds a FULL German banking licence (not just EMI), offering
 * deposit-taking, lending, and card issuing. ECB + BaFin dual oversight.
 * REST API with webhooks for real-time authorization.
 *
 * @see https://docs.solarisgroup.com/
 */
class SolarisCardIssuer implements CardIssuerInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $baseUrl,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $partnerId,
        private readonly LoggerInterface $logger,
    ) {}

    public function createVirtualCard(string $userId, string $cardholderName, string $currency = 'EUR'): array
    {
        $nameParts = explode(' ', $cardholderName, 2);
        $person = $this->request('POST', "/v1/persons", [
            'first_name' => $nameParts[0], 'last_name' => $nameParts[1] ?? $nameParts[0],
            'email' => "{$userId}@eupay.eu",
        ]);
        $account = $this->request('POST', "/v1/persons/{$person['id']}/accounts", [
            'type' => 'CHECKING', 'currency' => $currency,
        ]);
        $card = $this->request('POST', "/v1/cards", [
            'account_id' => $account['id'], 'type' => 'VIRTUAL_VISA_DEBIT',
            'person_id' => $person['id'], 'line_1' => $cardholderName,
        ]);
        return [
            'cardId' => $card['id'] ?? '', 'status' => strtoupper($card['status'] ?? 'ACTIVE'),
            'maskedPan' => $card['representation']['masked_pan'] ?? '',
            'last4' => $card['representation']['last_4_digits'] ?? '',
            'expiryMonth' => (int) ($card['representation']['expiration_month'] ?? 12),
            'expiryYear' => (int) ($card['representation']['expiration_year'] ?? 2028),
            'scheme' => 'VISA',
        ];
    }

    public function activateCard(string $cardId): array
    {
        $this->request('POST', "/v1/cards/{$cardId}/activate");
        return ['cardId' => $cardId, 'status' => 'ACTIVE'];
    }

    public function blockCard(string $cardId): array
    {
        $this->request('POST', "/v1/cards/{$cardId}/block");
        return ['cardId' => $cardId, 'status' => 'SUSPENDED'];
    }

    public function unblockCard(string $cardId): array
    {
        $this->request('POST', "/v1/cards/{$cardId}/unblock");
        return ['cardId' => $cardId, 'status' => 'ACTIVE'];
    }

    public function terminateCard(string $cardId): array
    {
        $this->request('POST', "/v1/cards/{$cardId}/close");
        return ['cardId' => $cardId, 'status' => 'TERMINATED'];
    }

    public function getCard(string $cardId): array
    {
        $c = $this->request('GET', "/v1/cards/{$cardId}");
        return [
            'cardId' => $c['id'] ?? '', 'status' => strtoupper($c['status'] ?? 'UNKNOWN'),
            'last4' => $c['representation']['last_4_digits'] ?? '',
            'expiryMonth' => (int) ($c['representation']['expiration_month'] ?? 0),
            'expiryYear' => (int) ($c['representation']['expiration_year'] ?? 0),
            'scheme' => 'VISA',
        ];
    }

    public function provisionDigitalCard(string $cardId, string $deviceId, string $deviceFingerprint): array
    {
        $r = $this->request('POST', "/v1/cards/{$cardId}/push_provisioning", [
            'device_id' => $deviceId, 'device_fingerprint' => $deviceFingerprint,
            'wallet_type' => 'HCE',
        ]);
        return [
            'dpan' => $r['digital_wallet']['dpan'] ?? '',
            'dpanExpiryMonth' => (int) ($r['digital_wallet']['expiry_month'] ?? 12),
            'dpanExpiryYear' => (int) ($r['digital_wallet']['expiry_year'] ?? 2028),
            'tokenReferenceId' => $r['digital_wallet']['token_reference_id'] ?? '',
            'tokenStatus' => 'ACTIVE',
            'emvKeys' => [
                'iccPrivateKey' => $r['emv_keys']['icc_private_key'] ?? '',
                'iccCertificate' => $r['emv_keys']['icc_certificate'] ?? '',
                'issuerPublicKey' => $r['emv_keys']['issuer_public_key'] ?? '',
            ],
        ];
    }

    public function generateEmvSessionKeys(string $tokenReferenceId, int $currentAtc): array
    {
        $r = $this->request('POST', "/v1/digital_wallets/{$tokenReferenceId}/emv_session", ['atc' => $currentAtc + 1]);
        return [
            'sessionKey' => $r['session_key'] ?? '', 'arqc' => $r['arqc'] ?? '',
            'atc' => (int) ($r['atc'] ?? $currentAtc + 1),
            'unpredictableNumber' => $r['unpredictable_number'] ?? bin2hex(random_bytes(4)),
        ];
    }

    public function deactivateDigitalCard(string $tokenReferenceId): array
    {
        $this->request('DELETE', "/v1/digital_wallets/{$tokenReferenceId}");
        return ['tokenReferenceId' => $tokenReferenceId, 'status' => 'TERMINATED'];
    }

    public function getDigitalCardStatus(string $tokenReferenceId): array
    {
        $r = $this->request('GET', "/v1/digital_wallets/{$tokenReferenceId}");
        return ['tokenReferenceId' => $tokenReferenceId, 'status' => strtoupper($r['status'] ?? 'UNKNOWN')];
    }

    public function loadFunds(string $cardId, int $amountCents, string $currency = 'EUR'): array
    {
        $card = $this->request('GET', "/v1/cards/{$cardId}");
        $accountId = $card['account_id'] ?? '';
        $r = $this->request('POST', "/v1/accounts/{$accountId}/transactions", [
            'amount' => ['value' => $amountCents, 'currency' => $currency], 'type' => 'SEPA_CREDIT',
        ]);
        return ['transactionId' => $r['id'] ?? '', 'amount' => $amountCents / 100, 'status' => 'COMPLETED'];
    }

    public function getCardBalance(string $cardId): array
    {
        $card = $this->request('GET', "/v1/cards/{$cardId}");
        $accountId = $card['account_id'] ?? '';
        $r = $this->request('GET', "/v1/accounts/{$accountId}");
        return [
            'available' => (int) ($r['balance']['available']['value'] ?? 0),
            'ledger' => (int) ($r['balance']['current']['value'] ?? 0),
            'currency' => $r['balance']['available']['currency'] ?? 'EUR',
        ];
    }

    private function request(string $method, string $path, array $body = []): array
    {
        $opts = ['headers' => [
            'Authorization' => 'Basic ' . base64_encode("{$this->clientId}:{$this->clientSecret}"),
            'Content-Type' => 'application/json', 'X-Solaris-Partner-ID' => $this->partnerId,
        ]];
        if (!empty($body) && $method !== 'GET') { $opts['json'] = $body; }
        try {
            return $this->httpClient->request($method, $this->baseUrl . $path, $opts)->toArray();
        } catch (\Throwable $e) {
            $this->logger->error('Solaris API error', ['path' => $path, 'error' => $e->getMessage()]);
            throw new OpenBankingException("Solaris error: {$e->getMessage()}", (int) $e->getCode(), $e);
        }
    }
}
