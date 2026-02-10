<?php

declare(strict_types=1);

namespace App\Service\CardIssuing;

/**
 * Card Issuer abstraction — any licensed EU card programme manager.
 *
 * Implementations: Marqeta (Visa), Adyen Issuing (Visa/MC),
 * Stripe Issuing (Visa), Enfuce (MC).
 *
 * NFC tap-to-pay requires a real card issuer — PSD2 CANNOT issue cards.
 * The issuer provides: PAN, DPAN (device PAN for tokenization), CVV,
 * expiry, and EMV session keys for HCE contactless payments.
 */
interface CardIssuerInterface
{
    // ── Card Lifecycle ──────────────────────────

    /**
     * Create a virtual debit card linked to a funding source.
     *
     * @return array{
     *     cardId: string,
     *     status: string,
     *     maskedPan: string,
     *     last4: string,
     *     expiryMonth: int,
     *     expiryYear: int,
     *     scheme: string,
     * }
     */
    public function createVirtualCard(string $userId, string $cardholderName, string $currency = 'EUR'): array;

    public function activateCard(string $cardId): array;
    public function blockCard(string $cardId): array;
    public function unblockCard(string $cardId): array;
    public function terminateCard(string $cardId): array;
    public function getCard(string $cardId): array;

    // ── NFC Tokenization (critical for HCE tap-to-pay) ──

    /**
     * Provision a Device PAN (DPAN) for HCE NFC payments.
     *
     * The DPAN replaces the real PAN during contactless transactions.
     * This is how Google Pay / Apple Pay work — and how EU Pay works.
     *
     * @return array{
     *     dpan: string,
     *     dpanExpiryMonth: int,
     *     dpanExpiryYear: int,
     *     tokenReferenceId: string,
     *     tokenStatus: string,
     *     emvKeys: array{
     *         iccPrivateKey: string,
     *         iccCertificate: string,
     *         issuerPublicKey: string,
     *     },
     * }
     */
    public function provisionDigitalCard(string $cardId, string $deviceId, string $deviceFingerprint): array;

    /**
     * Generate EMV session keys for a single NFC tap.
     *
     * Called before each contactless payment. The terminal verifies
     * the ARQC against the card network (Visa/Mastercard).
     *
     * @return array{
     *     sessionKey: string,
     *     arqc: string,
     *     atc: int,
     *     unpredictableNumber: string,
     * }
     */
    public function generateEmvSessionKeys(string $tokenReferenceId, int $currentAtc): array;

    public function deactivateDigitalCard(string $tokenReferenceId): array;
    public function getDigitalCardStatus(string $tokenReferenceId): array;

    // ── Funding ─────────────────────────────────

    /** Load funds onto card (called after PSD2 top-up completes) */
    public function loadFunds(string $cardId, int $amountCents, string $currency = 'EUR'): array;
    public function getCardBalance(string $cardId): array;
}
