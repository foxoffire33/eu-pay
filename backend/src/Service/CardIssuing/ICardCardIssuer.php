<?php

declare(strict_types=1);

namespace App\Service\CardIssuing;

use App\Service\OpenBankingException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * iCard — Bulgarian EMI with digital wallet + card issuing.
 *
 * License:  Bulgarian National Bank (BNB), EMI
 * Scheme:   Visa + Mastercard
 * Coverage: EU/EEA via passporting from Bulgaria
 * Powers:   iCard digital wallet app (1M+ users), P2P payments
 * Services: Virtual/physical card issuing, digital wallet, SEPA, SWIFT
 *
 * iCard combines a consumer-facing digital wallet with B2B card issuing
 * APIs. Strong presence in Southeast Europe (BG, RO, GR, HR).
 *
 * @see https://www.icard.com/
 */
class ICardCardIssuer implements CardIssuerInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly string $merchantId,
        private readonly LoggerInterface $logger,
    ) {}

    public function createVirtualCard(string $userId, string $cardholderName, string $currency = 'EUR'): array
    {
        $card = $this->request('POST', '/api/v1/cards', [
            'merchant_id' => $this->merchantId, 'cardholder_name' => $cardholderName,
            'external_id' => $userId, 'type' => 'VIRTUAL', 'currency' => $currency,
        ]);
        return [
            'cardId' => $card['id'] ?? '', 'status' => strtoupper($card['status'] ?? 'ACTIVE'),
            'maskedPan' => $card['masked_pan'] ?? '', 'last4' => $card['last4'] ?? '',
            'expiryMonth' => (int) ($card['expiry_month'] ?? 12),
            'expiryYear' => (int) ($card['expiry_year'] ?? 2028),
            'scheme' => strtoupper($card['scheme'] ?? 'VISA'),
        ];
    }

    public function activateCard(string $cardId): array { $this->request('POST', "/api/v1/cards/{$cardId}/activate"); return ['cardId' => $cardId, 'status' => 'ACTIVE']; }
    public function blockCard(string $cardId): array { $this->request('POST', "/api/v1/cards/{$cardId}/block"); return ['cardId' => $cardId, 'status' => 'SUSPENDED']; }
    public function unblockCard(string $cardId): array { $this->request('POST', "/api/v1/cards/{$cardId}/unblock"); return ['cardId' => $cardId, 'status' => 'ACTIVE']; }
    public function terminateCard(string $cardId): array { $this->request('DELETE', "/api/v1/cards/{$cardId}"); return ['cardId' => $cardId, 'status' => 'TERMINATED']; }
    public function getCard(string $cardId): array
    {
        $c = $this->request('GET', "/api/v1/cards/{$cardId}");
        return ['cardId' => $c['id'] ?? '', 'status' => strtoupper($c['status'] ?? 'UNKNOWN'), 'last4' => $c['last4'] ?? '',
            'expiryMonth' => (int) ($c['expiry_month'] ?? 0), 'expiryYear' => (int) ($c['expiry_year'] ?? 0),
            'scheme' => strtoupper($c['scheme'] ?? 'VISA')];
    }
    public function provisionDigitalCard(string $cardId, string $deviceId, string $deviceFingerprint): array
    {
        $r = $this->request('POST', "/api/v1/cards/{$cardId}/tokenize", ['device_id' => $deviceId, 'device_fingerprint' => $deviceFingerprint, 'type' => 'HCE']);
        return ['dpan' => $r['dpan'] ?? '', 'dpanExpiryMonth' => (int) ($r['expiry_month'] ?? 12), 'dpanExpiryYear' => (int) ($r['expiry_year'] ?? 2028),
            'tokenReferenceId' => $r['token_id'] ?? '', 'tokenStatus' => 'ACTIVE',
            'emvKeys' => ['iccPrivateKey' => $r['emv']['icc_private_key'] ?? '', 'iccCertificate' => $r['emv']['icc_certificate'] ?? '', 'issuerPublicKey' => $r['emv']['issuer_public_key'] ?? '']];
    }
    public function generateEmvSessionKeys(string $tokenReferenceId, int $currentAtc): array
    {
        $r = $this->request('POST', "/api/v1/tokens/{$tokenReferenceId}/session", ['atc' => $currentAtc + 1]);
        return ['sessionKey' => $r['session_key'] ?? '', 'arqc' => $r['arqc'] ?? '', 'atc' => (int) ($r['atc'] ?? $currentAtc + 1), 'unpredictableNumber' => $r['un'] ?? bin2hex(random_bytes(4))];
    }
    public function deactivateDigitalCard(string $tokenReferenceId): array { $this->request('DELETE', "/api/v1/tokens/{$tokenReferenceId}"); return ['tokenReferenceId' => $tokenReferenceId, 'status' => 'TERMINATED']; }
    public function getDigitalCardStatus(string $tokenReferenceId): array { $r = $this->request('GET', "/api/v1/tokens/{$tokenReferenceId}"); return ['tokenReferenceId' => $tokenReferenceId, 'status' => strtoupper($r['status'] ?? 'UNKNOWN')]; }
    public function loadFunds(string $cardId, int $amountCents, string $currency = 'EUR'): array { $r = $this->request('POST', "/api/v1/cards/{$cardId}/load", ['amount' => $amountCents, 'currency' => $currency]); return ['transactionId' => $r['id'] ?? '', 'amount' => $amountCents / 100, 'status' => 'COMPLETED']; }
    public function getCardBalance(string $cardId): array { $r = $this->request('GET', "/api/v1/cards/{$cardId}/balance"); return ['available' => (int) ($r['available'] ?? 0), 'ledger' => (int) ($r['ledger'] ?? 0), 'currency' => 'EUR']; }
    private function request(string $method, string $path, array $body = []): array
    {
        $opts = ['headers' => ['Authorization' => "Bearer {$this->apiKey}", 'Content-Type' => 'application/json']];
        if (!empty($body) && $method !== 'GET') { $opts['json'] = $body; }
        try { return $this->httpClient->request($method, $this->baseUrl . $path, $opts)->toArray();
        } catch (\Throwable $e) { $this->logger->error('iCard API error', ['path' => $path, 'error' => $e->getMessage()]); throw new OpenBankingException("iCard error: {$e->getMessage()}", (int) $e->getCode(), $e); }
    }
}
