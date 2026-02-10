<?php

declare(strict_types=1);

namespace App\Service\CardIssuing;

use App\Service\OpenBankingException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Bankable — Luxembourg EMI powering white-label banking.
 *
 * License:  CSSF (Commission de Surveillance du Secteur Financier), Luxembourg, EMI
 * Scheme:   Visa + Mastercard
 * Coverage: EU/EEA via passporting from Luxembourg
 * Services: Card issuing, multi-currency accounts, SEPA, FX, compliance
 *
 * Luxembourg jurisdiction offers: EU passporting, tax efficiency,
 * CSSF regulatory clarity, and proximity to EU institutions.
 * Bankable provides APIs for BaaS and embedded finance.
 *
 * @see https://www.bnkbl.com/
 */
class BankableCardIssuer implements CardIssuerInterface
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
        $holder = $this->request('POST', '/api/v2/cardholders', ['name' => $cardholderName, 'external_id' => $userId, 'programme_id' => $this->programmeId]);
        $card = $this->request('POST', '/api/v2/cards', ['cardholder_id' => $holder['id'], 'type' => 'VIRTUAL', 'currency' => $currency]);
        return ['cardId' => $card['id'] ?? '', 'status' => strtoupper($card['status'] ?? 'ACTIVE'), 'maskedPan' => $card['masked_pan'] ?? '', 'last4' => $card['last4'] ?? '',
            'expiryMonth' => (int) ($card['expiry_month'] ?? 12), 'expiryYear' => (int) ($card['expiry_year'] ?? 2028), 'scheme' => strtoupper($card['scheme'] ?? 'VISA')];
    }

    public function activateCard(string $cardId): array { $this->request('POST', "/api/v2/cards/{$cardId}/activate"); return ['cardId' => $cardId, 'status' => 'ACTIVE']; }
    public function blockCard(string $cardId): array { $this->request('POST', "/api/v2/cards/{$cardId}/block"); return ['cardId' => $cardId, 'status' => 'SUSPENDED']; }
    public function unblockCard(string $cardId): array { $this->request('POST', "/api/v2/cards/{$cardId}/unblock"); return ['cardId' => $cardId, 'status' => 'ACTIVE']; }
    public function terminateCard(string $cardId): array { $this->request('DELETE', "/api/v2/cards/{$cardId}"); return ['cardId' => $cardId, 'status' => 'TERMINATED']; }
    public function getCard(string $cardId): array
    {
        $c = $this->request('GET', "/api/v2/cards/{$cardId}");
        return ['cardId' => $c['id'] ?? '', 'status' => strtoupper($c['status'] ?? 'UNKNOWN'), 'last4' => $c['last4'] ?? '',
            'expiryMonth' => (int) ($c['expiry_month'] ?? 0), 'expiryYear' => (int) ($c['expiry_year'] ?? 0), 'scheme' => strtoupper($c['scheme'] ?? 'VISA')];
    }
    public function provisionDigitalCard(string $cardId, string $deviceId, string $deviceFingerprint): array
    {
        $r = $this->request('POST', "/api/v2/cards/{$cardId}/tokenize", ['device_id' => $deviceId, 'device_fingerprint' => $deviceFingerprint, 'wallet_type' => 'HCE']);
        return ['dpan' => $r['dpan'] ?? '', 'dpanExpiryMonth' => (int) ($r['expiry_month'] ?? 12), 'dpanExpiryYear' => (int) ($r['expiry_year'] ?? 2028),
            'tokenReferenceId' => $r['token_id'] ?? '', 'tokenStatus' => 'ACTIVE',
            'emvKeys' => ['iccPrivateKey' => $r['emv']['icc_private_key'] ?? '', 'iccCertificate' => $r['emv']['icc_certificate'] ?? '', 'issuerPublicKey' => $r['emv']['issuer_public_key'] ?? '']];
    }
    public function generateEmvSessionKeys(string $tokenReferenceId, int $currentAtc): array
    {
        $r = $this->request('POST', "/api/v2/tokens/{$tokenReferenceId}/emv-session", ['atc' => $currentAtc + 1]);
        return ['sessionKey' => $r['session_key'] ?? '', 'arqc' => $r['arqc'] ?? '', 'atc' => (int) ($r['atc'] ?? $currentAtc + 1), 'unpredictableNumber' => $r['un'] ?? bin2hex(random_bytes(4))];
    }
    public function deactivateDigitalCard(string $tokenReferenceId): array { $this->request('DELETE', "/api/v2/tokens/{$tokenReferenceId}"); return ['tokenReferenceId' => $tokenReferenceId, 'status' => 'TERMINATED']; }
    public function getDigitalCardStatus(string $tokenReferenceId): array { $r = $this->request('GET', "/api/v2/tokens/{$tokenReferenceId}"); return ['tokenReferenceId' => $tokenReferenceId, 'status' => strtoupper($r['status'] ?? 'UNKNOWN')]; }
    public function loadFunds(string $cardId, int $amountCents, string $currency = 'EUR'): array { $r = $this->request('POST', "/api/v2/cards/{$cardId}/load", ['amount' => $amountCents, 'currency' => $currency]); return ['transactionId' => $r['id'] ?? '', 'amount' => $amountCents / 100, 'status' => 'COMPLETED']; }
    public function getCardBalance(string $cardId): array { $r = $this->request('GET', "/api/v2/cards/{$cardId}/balance"); return ['available' => (int) ($r['available'] ?? 0), 'ledger' => (int) ($r['ledger'] ?? 0), 'currency' => 'EUR']; }
    private function request(string $method, string $path, array $body = []): array
    {
        $opts = ['headers' => ['Authorization' => "Bearer {$this->apiKey}", 'Content-Type' => 'application/json']];
        if (!empty($body) && $method !== 'GET') { $opts['json'] = $body; }
        try { return $this->httpClient->request($method, $this->baseUrl . $path, $opts)->toArray();
        } catch (\Throwable $e) { $this->logger->error('Bankable API error', ['path' => $path, 'error' => $e->getMessage()]); throw new OpenBankingException("Bankable error: {$e->getMessage()}", (int) $e->getCode(), $e); }
    }
}
