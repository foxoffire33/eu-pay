<?php

declare(strict_types=1);

namespace App\Service\DigitalEuro;

use Psr\Log\LoggerInterface;

/**
 * Digital Euro pre-launch stub.
 *
 * Returns structured "not yet available" responses for all operations.
 * This allows EU Pay to build UI flows, test integration points, and
 * onboard users for the digital euro BEFORE the ECB pilot goes live.
 *
 * Replace with DigitalEuroDesp (DESP API client) once:
 *   1. EU Digital Euro Regulation is adopted (expected H1 2026)
 *   2. ECB publishes pilot PSP API specs (expected Q2-Q3 2027)
 *   3. EU Pay is accepted as pilot PSP participant
 *
 * This stub is also used in tests to verify digital euro flow logic.
 */
class DigitalEuroStub implements DigitalEuroInterface
{
    private const HOLDING_LIMIT_CENTS = 300000; // €3,000 — ECB analysis range

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function openDigitalEuroAccount(string $userId, string $fullName): array
    {
        $this->logStub(__FUNCTION__);
        return [
            'deaId' => '',
            'accessNumber' => '',
            'holdingLimit' => self::HOLDING_LIMIT_CENTS,
            'currency' => 'EUR',
            'status' => 'NOT_AVAILABLE',
            '_notice' => 'Digital euro not yet launched. ECB pilot expected H2 2027, issuance 2029.',
        ];
    }

    public function closeDigitalEuroAccount(string $deaId): array
    {
        $this->logStub(__FUNCTION__);
        return ['status' => 'NOT_AVAILABLE'];
    }

    public function getBalance(string $deaId): array
    {
        $this->logStub(__FUNCTION__);
        return [
            'available' => 0,
            'holdingLimit' => self::HOLDING_LIMIT_CENTS,
            'currency' => 'EUR',
            'status' => 'NOT_AVAILABLE',
        ];
    }

    public function initiateP2PPayment(string $fromDeaId, string $toAlias, int $amountCents): array
    {
        $this->logStub(__FUNCTION__);
        return [
            'paymentId' => '',
            'status' => 'NOT_AVAILABLE',
            'amount' => $amountCents,
            'currency' => 'EUR',
        ];
    }

    public function initiatePosPayment(string $deaId, int $amountCents, string $merchantId, string $method = 'nfc'): array
    {
        $this->logStub(__FUNCTION__);
        return [
            'paymentId' => '',
            'paymentToken' => '',
            'method' => $method,
            'expiresAt' => '',
            'status' => 'NOT_AVAILABLE',
        ];
    }

    public function initiateEcommercePayment(string $deaId, int $amountCents, string $merchantId, string $callbackUrl): array
    {
        $this->logStub(__FUNCTION__);
        return [
            'paymentId' => '',
            'redirectUrl' => '',
            'status' => 'NOT_AVAILABLE',
        ];
    }

    public function fundOfflineBalance(string $deaId, int $amountCents): array
    {
        $this->logStub(__FUNCTION__);
        return [
            'offlineBalance' => 0,
            'currency' => 'EUR',
            'maxOfflineAmount' => 0,
            'status' => 'NOT_AVAILABLE',
        ];
    }

    public function syncOfflineTransactions(string $deaId, array $offlineTransactions): array
    {
        $this->logStub(__FUNCTION__);
        return ['synced' => 0, 'failed' => 0, 'newOnlineBalance' => 0, 'status' => 'NOT_AVAILABLE'];
    }

    public function fundFromBankAccount(string $deaId, int $amountCents, string $iban): array
    {
        $this->logStub(__FUNCTION__);
        return ['status' => 'NOT_AVAILABLE'];
    }

    public function sweepToBank(string $deaId, int $amountCents, string $iban): array
    {
        $this->logStub(__FUNCTION__);
        return ['status' => 'NOT_AVAILABLE'];
    }

    public function registerAlias(string $deaId, string $aliasType, string $aliasValue): array
    {
        $this->logStub(__FUNCTION__);
        return ['status' => 'NOT_AVAILABLE'];
    }

    public function lookupAlias(string $aliasType, string $aliasValue): array
    {
        $this->logStub(__FUNCTION__);
        return ['found' => false, 'status' => 'NOT_AVAILABLE'];
    }

    public function isAvailable(): bool
    {
        return false; // Will return true once ECB DESP API is live
    }

    public function getRegulationParameters(): array
    {
        return [
            'holdingLimitCents' => self::HOLDING_LIMIT_CENTS,
            'holdingLimitEur' => self::HOLDING_LIMIT_CENTS / 100,
            'interestRate' => 0.0, // DEA is interest-free by design
            'offlineSupported' => true,
            'onlineSupported' => true,
            'p2pSupported' => true,
            'posNfcSupported' => true,
            'posQrSupported' => true,
            'ecommerceSupported' => true,
            'freeForConsumers' => true,
            'merchantFeeCapped' => true,
            'privacyModel' => 'pseudonymous', // ECB cannot see balances or patterns
            'apiStandard' => 'Berlin Group NextGenPSD2 (RESTful)',
            'messagingStandard' => 'ISO 20022',
            'regulationStatus' => 'PENDING_ADOPTION', // EU Parliament vote expected H1 2026
            'pilotExpected' => '2027-H2',
            'issuanceExpected' => '2029',
            'ecbInfoUrl' => 'https://www.ecb.europa.eu/euro/digital_euro/progress/html/index.en.html',
        ];
    }

    private function logStub(string $method): void
    {
        $this->logger->info("Digital Euro stub called: {$method} — service not yet available");
    }
}
