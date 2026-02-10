<?php

declare(strict_types=1);

namespace App\Service\CardIssuing;

use App\Service\OpenBankingException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Paynovate — Belgian EMI with full acquiring + issuing.
 *
 * License:  National Bank of Belgium (NBB), EMI
 * Scheme:   Visa + Mastercard + China UnionPay + Bancontact (principal member)
 * Coverage: All 30 EEA countries (passported) + UK (FCA AEMI)
 * Powers:   €200M+/month processed, 150+ currencies, 15 settlement currencies
 * Services: Card issuing, BIN sponsorship, payment acquiring, IBAN accounts
 * SWIFT:    PAYVBEB2 — SEPA SCT, SCT Inst, SDD, SDD B2B
 *
 * Belgian EMI with Visa + Mastercard principal membership.
 * Offers BIN sponsorship for fintechs without their own EMI licence.
 *
 * @see https://www.paynovate.com/
 */
class PaynovateCardIssuer implements CardIssuerInterface
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
        $holder = $this->request('POST', '/api/v1/cardholders', [
            'merchant_id' => $this->merchantId,
            'name' => $cardholderName,
            'external_reference' => $userId,
            'currency' => $currency,
        ]);

        $card = $this->request('POST', '/api/v1/cards', [
            'cardholder_id' => $holder['id'],
            'type' => 'VIRTUAL',
            'scheme' => 'VISA',
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
        $this->request('PUT', "/api/v1/cards/{$cardId}/status", ['status' => 'ACTIVE']);
        return ['cardId' => $cardId, 'status' => 'ACTIVE'];
    }

    public function blockCard(string $cardId): array
    {
        $this->request('PUT', "/api/v1/cards/{$cardId}/status", ['status' => 'BLOCKED']);
        return ['cardId' => $cardId, 'status' => 'SUSPENDED'];
    }

    public function unblockCard(string $cardId): array { return $this->activateCard($cardId); }

    public function terminateCard(string $cardId): array
    {
        $this->request('PUT', "/api/v1/cards/{$cardId}/status", ['status' => 'CLOSED']);
        return ['cardId' => $cardId, 'status' => 'TERMINATED'];
    }

    public function getCard(string $cardId): array
    {
        $card = $this->request('GET', "/api/v1/cards/{$cardId}");
        return [
            'cardId' => $card['id'] ?? '', 'status' => strtoupper($card['status'] ?? 'UNKNOWN'),
            'last4' => $card['last4'] ?? '', 'expiryMonth' => (int) ($card['expiry_month'] ?? 0),
            'expiryYear' => (int) ($card['expiry_year'] ?? 0),
            'scheme' => strtoupper($card['scheme'] ?? 'VISA'),
        ];
    }

    public function provisionDigitalCard(string $cardId, string $deviceId, string $deviceFingerprint): array
    {
        $r = $this->request('POST', "/api/v1/cards/{$cardId}/tokenize", [
            'device_id' => $deviceId, 'device_fingerprint' => $deviceFingerprint, 'type' => 'HCE',
        ]);
        return [
            'dpan' => $r['token']['dpan'] ?? '', 'dpanExpiryMonth' => (int) ($r['token']['expiry_month'] ?? 12),
            'dpanExpiryYear' => (int) ($r['token']['expiry_year'] ?? 2028),
            'tokenReferenceId' => $r['token']['id'] ?? '', 'tokenStatus' => 'ACTIVE',
            'emvKeys' => [
                'iccPrivateKey' => $r['emv']['icc_private_key'] ?? '',
                'iccCertificate' => $r['emv']['icc_certificate'] ?? '',
                'issuerPublicKey' => $r['emv']['issuer_public_key'] ?? '',
            ],
        ];
    }

    public function generateEmvSessionKeys(string $tokenReferenceId, int $currentAtc): array
    {
        $r = $this->request('POST', "/api/v1/tokens/{$tokenReferenceId}/emv-session", ['atc' => $currentAtc + 1]);
        return [
            'sessionKey' => $r['session_key'] ?? '', 'arqc' => $r['arqc'] ?? '',
            'atc' => (int) ($r['atc'] ?? $currentAtc + 1),
            'unpredictableNumber' => $r['unpredictable_number'] ?? bin2hex(random_bytes(4)),
        ];
    }

    public function deactivateDigitalCard(string $tokenReferenceId): array
    {
        $this->request('DELETE', "/api/v1/tokens/{$tokenReferenceId}");
        return ['tokenReferenceId' => $tokenReferenceId, 'status' => 'TERMINATED'];
    }

    public function getDigitalCardStatus(string $tokenReferenceId): array
    {
        $r = $this->request('GET', "/api/v1/tokens/{$tokenReferenceId}");
        return ['tokenReferenceId' => $tokenReferenceId, 'status' => strtoupper($r['status'] ?? 'UNKNOWN')];
    }

    public function loadFunds(string $cardId, int $amountCents, string $currency = 'EUR'): array
    {
        $r = $this->request('POST', "/api/v1/cards/{$cardId}/load", ['amount' => $amountCents, 'currency' => $currency]);
        return ['transactionId' => $r['id'] ?? '', 'amount' => $amountCents / 100, 'status' => 'COMPLETED'];
    }

    public function getCardBalance(string $cardId): array
    {
        $r = $this->request('GET', "/api/v1/cards/{$cardId}/balance");
        return ['available' => (int) ($r['available'] ?? 0), 'ledger' => (int) ($r['ledger'] ?? 0), 'currency' => 'EUR'];
    }

    private function request(string $method, string $path, array $body = []): array
    {
        $options = ['headers' => ['Authorization' => "Bearer {$this->apiKey}", 'Content-Type' => 'application/json']];
        if (!empty($body) && $method !== 'GET') { $options['json'] = $body; }
        try {
            return $this->httpClient->request($method, $this->baseUrl . $path, $options)->toArray();
        } catch (\Throwable $e) {
            $this->logger->error('Paynovate API error', ['path' => $path, 'error' => $e->getMessage()]);
            throw new OpenBankingException("Paynovate error: {$e->getMessage()}", (int) $e->getCode(), $e);
        }
    }
}
