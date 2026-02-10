<?php

declare(strict_types=1);

namespace App\Service;

/**
 * PSD2 Open Banking abstraction — AISP + PISP.
 *
 * Implementations:
 *   - OpenBankingService (production — Rabobank PSD2 API)
 *   - DevOpenBankingService (dev stub — mock data, no API keys needed)
 */
interface OpenBankingInterface
{
    // ── PISP: Payment Initiation ──

    /** @return array{paymentId: string, authorisationUrl: string, status: string} */
    public function initiateSepaTransfer(
        string $debtorIban,
        string $debtorName,
        string $creditorIban,
        string $creditorName,
        string $amountEur,
        string $reference,
    ): array;

    /** @return array{paymentId: string, authorisationUrl: string, status: string} */
    public function initiateIdealPayment(
        string $creditorIban,
        string $creditorName,
        string $amountEur,
        string $reference,
        ?string $debtorBic = null,
    ): array;

    public function getPaymentStatus(string $paymentProduct, string $paymentId): array;

    // ── AISP: Account Information ──

    /** @return array{consentId: string, authorisationUrl: string, validUntil: string} */
    public function createAccountConsent(string $iban, int $validDays = 90): array;

    public function getAccountBalance(string $consentId, string $accountId): array;

    public function getAccountTransactions(
        string $consentId,
        string $accountId,
        string $dateFrom,
        ?string $dateTo = null,
    ): array;

    // ── Bank Registry ──

    public function getSupportedBanks(): array;
    public function getBanksByCountry(string $countryCode): array;
    public function getSupportedCountries(): array;
}
