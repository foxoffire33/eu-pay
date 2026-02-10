<?php

declare(strict_types=1);

namespace App\Service\DigitalEuro;

/**
 * Digital Euro (ECB CBDC) — preparedness interface for EU Pay.
 *
 * ┌─────────────────────────────────────────────────────────────────────┐
 * │  TIMELINE (as of February 2026)                                    │
 * │  ● Oct 2025: ECB Governing Council moved to next phase             │
 * │  ● Q1 2026:  Call for PSP expression of interest                   │
 * │  ● H1 2026:  European Parliament vote on Digital Euro Regulation   │
 * │  ● H2 2027:  Pilot with selected PSPs (12 months)                  │
 * │  ● 2029:     Potential first issuance of the digital euro          │
 * └─────────────────────────────────────────────────────────────────────┘
 *
 * ARCHITECTURE (two-tier model):
 *   ECB (Eurosystem) ←→ PSP intermediaries ←→ End users
 *   ● ECB issues digital euro, manages DESP (Digital Euro Service Platform)
 *   ● PSPs distribute via wallets, handle onboarding/KYC, fraud mgmt
 *   ● RESTful APIs following Berlin Group NextGenPSD2 standards
 *   ● ISO 20022 messaging for settlement
 *   ● Digital Euro Access Gateway connects PSPs to DESP
 *
 * KEY FEATURES:
 *   ● Holding limit: up to €3,000 per person (no financial stability risk)
 *   ● Online payments: POS (NFC/QR), e-commerce, P2P
 *   ● Offline payments: device-to-device without internet
 *   ● Free for basic consumer use; merchants pay capped fees
 *   ● Privacy: ECB cannot see user balances or payment patterns
 *   ● Pseudonymous identifiers for transaction processing
 *
 * EU PAY INTEGRATION STRATEGY:
 *   When the digital euro launches, EU Pay can act as a PSP intermediary
 *   or integrate via a PSP partner. This interface abstracts the DESP API
 *   so EU Pay can offer digital euro as a third payment rail alongside
 *   PSD2 (bank transfers) and card issuing (NFC tap-to-pay).
 *
 * @see https://www.ecb.europa.eu/euro/digital_euro/progress/html/index.en.html
 */
interface DigitalEuroInterface
{
    // ── Wallet Management ───────────────────────

    /**
     * Open a Digital Euro Account (DEA) for a user via PSP.
     *
     * The DEA is interest-free and subject to the holding limit.
     * PSP handles KYC/KYB and onboarding via DESP.
     *
     * @return array{
     *     deaId: string,
     *     accessNumber: string,
     *     holdingLimit: int,
     *     currency: string,
     *     status: string,
     * }
     */
    public function openDigitalEuroAccount(string $userId, string $fullName): array;

    /** Close a Digital Euro Account (sweep remaining balance first) */
    public function closeDigitalEuroAccount(string $deaId): array;

    /** Get DEA balance (separate from card balance / bank balance) */
    public function getBalance(string $deaId): array;

    // ── Online Payments ─────────────────────────

    /**
     * Initiate a person-to-person (P2P) digital euro payment.
     *
     * Uses alias lookup (phone/email → DEA) via DESP.
     * Settlement is instant via TARGET/DESP infrastructure.
     *
     * @return array{
     *     paymentId: string,
     *     status: string,
     *     amount: int,
     *     currency: string,
     * }
     */
    public function initiateP2PPayment(string $fromDeaId, string $toAlias, int $amountCents): array;

    /**
     * Initiate a payment at Point of Sale (POS).
     *
     * For NFC: generates a payment token for contactless tap
     * For QR: generates a QR code payload for merchant scanning
     *
     * @param string $method 'nfc' | 'qr'
     * @return array{
     *     paymentId: string,
     *     paymentToken: string,
     *     method: string,
     *     expiresAt: string,
     * }
     */
    public function initiatePosPayment(string $deaId, int $amountCents, string $merchantId, string $method = 'nfc'): array;

    /**
     * Initiate an e-commerce payment.
     *
     * @return array{
     *     paymentId: string,
     *     redirectUrl: string,
     *     status: string,
     * }
     */
    public function initiateEcommercePayment(string $deaId, int $amountCents, string $merchantId, string $callbackUrl): array;

    // ── Offline Payments ────────────────────────

    /**
     * Pre-fund offline balance from online DEA.
     *
     * Moves funds from the online DEA to a local device-stored
     * offline balance for payments without internet connectivity.
     *
     * @return array{
     *     offlineBalance: int,
     *     currency: string,
     *     maxOfflineAmount: int,
     * }
     */
    public function fundOfflineBalance(string $deaId, int $amountCents): array;

    /**
     * Sync offline transactions back to DESP when connectivity returns.
     *
     * @param array $offlineTransactions List of offline tx payloads
     * @return array{
     *     synced: int,
     *     failed: int,
     *     newOnlineBalance: int,
     * }
     */
    public function syncOfflineTransactions(string $deaId, array $offlineTransactions): array;

    // ── Funding & Waterfall ─────────────────────

    /**
     * Fund DEA from linked bank account (PSD2 PISP).
     *
     * Subject to holding limit — excess is rejected.
     */
    public function fundFromBankAccount(string $deaId, int $amountCents, string $iban): array;

    /**
     * Sweep DEA balance back to linked bank account.
     *
     * Enables "reverse waterfall" — excess funds above a threshold
     * are automatically moved to the user's bank account.
     */
    public function sweepToBank(string $deaId, int $amountCents, string $iban): array;

    // ── Alias Management ────────────────────────

    /** Register a payment alias (phone/email) for receiving digital euro */
    public function registerAlias(string $deaId, string $aliasType, string $aliasValue): array;

    /** Look up a DEA by alias for P2P payments */
    public function lookupAlias(string $aliasType, string $aliasValue): array;

    // ── Status & Compliance ─────────────────────

    /** Check if digital euro service is available (pre-launch: always false) */
    public function isAvailable(): bool;

    /** Get current regulatory parameters (holding limit, fee caps, etc.) */
    public function getRegulationParameters(): array;
}
