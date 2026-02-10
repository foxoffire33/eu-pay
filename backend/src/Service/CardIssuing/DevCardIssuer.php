<?php

declare(strict_types=1);

namespace App\Service\CardIssuing;

use Psr\Log\LoggerInterface;

/**
 * Development/local card issuer stub — returns mock card data.
 *
 * Used when no real card issuer API keys are configured.
 * Simulates Visa virtual card issuance for local testing.
 */
class DevCardIssuer implements CardIssuerInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function createVirtualCard(string $userId, string $cardholderName, string $currency = 'EUR'): array
    {
        $last4 = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $cardId = 'dev_card_' . bin2hex(random_bytes(12));

        $this->logger->info('DEV: Virtual card issued (mock)', [
            'cardId' => $cardId,
            'last4' => $last4,
            'userId' => $userId,
        ]);

        return [
            'cardId' => $cardId,
            'status' => 'ACTIVE',
            'maskedPan' => '************' . $last4,
            'last4' => $last4,
            'expiryMonth' => (int) date('m'),
            'expiryYear' => (int) date('Y') + 3,
            'scheme' => 'VISA',
        ];
    }

    public function activateCard(string $cardId): array
    {
        return ['cardId' => $cardId, 'status' => 'ACTIVE'];
    }

    public function blockCard(string $cardId): array
    {
        return ['cardId' => $cardId, 'status' => 'SUSPENDED'];
    }

    public function unblockCard(string $cardId): array
    {
        return ['cardId' => $cardId, 'status' => 'ACTIVE'];
    }

    public function terminateCard(string $cardId): array
    {
        return ['cardId' => $cardId, 'status' => 'TERMINATED'];
    }

    public function getCard(string $cardId): array
    {
        return [
            'cardId' => $cardId,
            'status' => 'ACTIVE',
            'last4' => '0000',
            'expiryMonth' => (int) date('m'),
            'expiryYear' => (int) date('Y') + 3,
            'scheme' => 'VISA',
        ];
    }

    public function provisionDigitalCard(string $cardId, string $deviceId, string $deviceFingerprint): array
    {
        $tokenRef = hash('sha256', $cardId . ':' . $deviceId . ':' . $deviceFingerprint);
        $iccSeed = hash('sha256', 'dev_icc_' . $tokenRef, true);
        $issuerSeed = hash('sha256', 'dev_issuer_' . $tokenRef, true);

        return [
            'dpan' => '4000000000001234',
            'dpanExpiryMonth' => (int) date('m'),
            'dpanExpiryYear' => (int) date('Y') + 3,
            'tokenReferenceId' => $tokenRef,
            'tokenStatus' => 'ACTIVE',
            'emvKeys' => [
                'iccPrivateKey' => base64_encode($iccSeed),
                'iccCertificate' => base64_encode(hash('sha256', $iccSeed . ':cert', true)),
                'issuerPublicKey' => base64_encode($issuerSeed),
            ],
        ];
    }

    public function generateEmvSessionKeys(string $tokenReferenceId, int $currentAtc): array
    {
        $nextAtc = $currentAtc + 1;
        $sessionKey = hash_hmac('sha256', (string) $nextAtc, $tokenReferenceId);
        $un = bin2hex(random_bytes(4));
        $arqc = hash_hmac('sha256', $nextAtc . $un, $sessionKey);

        return [
            'sessionKey' => $sessionKey,
            'arqc' => substr($arqc, 0, 16),
            'atc' => $nextAtc,
            'unpredictableNumber' => $un,
        ];
    }

    public function deactivateDigitalCard(string $tokenReferenceId): array
    {
        return ['tokenReferenceId' => $tokenReferenceId, 'status' => 'TERMINATED'];
    }

    public function getDigitalCardStatus(string $tokenReferenceId): array
    {
        return ['tokenReferenceId' => $tokenReferenceId, 'status' => 'ACTIVE'];
    }

    public function loadFunds(string $cardId, int $amountCents, string $currency = 'EUR'): array
    {
        return [
            'transactionId' => 'dev_tx_' . bin2hex(random_bytes(8)),
            'amount' => $amountCents / 100,
            'status' => 'SUCCEEDED',
        ];
    }

    public function getCardBalance(string $cardId): array
    {
        return ['available' => 10000, 'ledger' => 10000, 'currency' => 'EUR'];
    }
}
