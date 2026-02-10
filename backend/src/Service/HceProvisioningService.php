<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Card;
use App\Entity\HceToken;
use App\Entity\User;
use App\Repository\HceTokenRepository;
use App\Service\CardIssuing\CardIssuerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * HCE NFC tap-to-pay provisioning.
 *
 * This is the critical bridge between the card issuer (Marqeta/Adyen) and
 * the Android HCE service that handles contactless APDU communication.
 *
 * Flow for each NFC tap:
 *  1. User taps phone → Android HCE service activates
 *  2. POS terminal sends SELECT PPSE → HCE responds with AID
 *  3. POS sends GET PROCESSING OPTIONS → HCE returns DPAN + EMV data
 *  4. POS sends GENERATE AC → HCE computes ARQC using session keys
 *  5. POS → acquirer → Visa/Mastercard → card issuer → approve/decline
 *
 * The DPAN (Device PAN) and EMV keys come from the card issuer, NOT PSD2.
 */
class HceProvisioningService
{
    public function __construct(
        private readonly CardIssuerInterface $cardIssuer,
        private readonly EntityManagerInterface $em,
        private readonly HceTokenRepository $hceTokenRepo,
        private readonly CardEncryptionService $encryption,
        private readonly LoggerInterface $logger,
        private readonly int $sessionKeyTtl,
    ) {}

    /**
     * Provision a card for NFC tap-to-pay on a specific device.
     *
     * Creates a DPAN (Device PAN) at the card issuer and stores the
     * tokenized card data + EMV keys locally for offline tap-to-pay.
     */
    public function provisionCard(User $user, Card $card, string $deviceFingerprint): HceToken
    {
        // Verify card is active at the issuer
        $issuerCard = $this->cardIssuer->getCard($card->getExternalCardId());
        if (!in_array($issuerCard['status'], ['ACTIVE', 'UNVERIFIED'])) {
            throw new \RuntimeException('Card is not active at the issuer. Status: ' . $issuerCard['status']);
        }

        // Deactivate existing tokens for this card on this device
        $existing = $this->hceTokenRepo->findActiveByCardAndDevice($card, $deviceFingerprint);
        foreach ($existing as $oldToken) {
            try {
                $this->cardIssuer->deactivateDigitalCard($oldToken->getTokenReferenceId());
            } catch (\Throwable $e) {
                $this->logger->warning('Failed to deactivate old DPAN', ['error' => $e->getMessage()]);
            }
            $oldToken->setStatus('DEACTIVATED');
        }

        // Provision DPAN at the card issuer (Marqeta/Adyen)
        $digitalCard = $this->cardIssuer->provisionDigitalCard(
            cardId: $card->getExternalCardId(),
            deviceId: $user->getId()->toRfc4122() . ':' . substr($deviceFingerprint, 0, 16),
            deviceFingerprint: $deviceFingerprint,
        );

        // Create HCE token with DPAN and EMV key material
        $token = new HceToken();
        $token->setCard($card);
        $token->setUser($user);
        $token->setExternalCardId($card->getExternalCardId());
        $token->setDeviceFingerprint($deviceFingerprint);
        $token->setDpan($digitalCard['dpan']);
        $token->setExpiryMonth($digitalCard['dpanExpiryMonth']);
        $token->setExpiryYear($digitalCard['dpanExpiryYear']);
        $token->setTokenReferenceId($digitalCard['tokenReferenceId']);
        $token->setCardScheme($card->getScheme());
        $token->setStatus('ACTIVE');
        $token->setAtc(0);
        $token->setExpiresAt(new \DateTimeImmutable("+{$this->sessionKeyTtl} seconds"));

        // Encrypt EMV keys (stored encrypted, decrypted only on Android device)
        $token->setEncryptedEmvKeys($this->encryption->encrypt(json_encode($digitalCard['emvKeys'])));

        // Generate initial session key for first tap
        $session = $this->cardIssuer->generateEmvSessionKeys($digitalCard['tokenReferenceId'], 0);
        $token->setSessionKey($this->encryption->encrypt($session['sessionKey']));
        $token->setAtc($session['atc']);

        $this->em->persist($token);
        $this->em->flush();

        $this->logger->info('HCE token provisioned with DPAN', [
            'tokenId' => $token->getId()->toRfc4122(),
            'cardId' => $card->getId()->toRfc4122(),
            'scheme' => $card->getScheme(),
        ]);

        return $token;
    }

    /**
     * Get the EMV payment payload for an NFC tap.
     *
     * Returns the data the Android HCE service needs to respond to
     * the POS terminal's APDU commands.
     */
    public function getPaymentPayload(HceToken $token): array
    {
        if ($token->getStatus() !== 'ACTIVE') {
            throw new \RuntimeException('Token is not active');
        }

        if ($token->getExpiresAt() < new \DateTimeImmutable()) {
            throw new \RuntimeException('Token has expired — refresh required');
        }

        return [
            'dpan' => $token->getDpan(),
            'expiryMonth' => $token->getExpiryMonth(),
            'expiryYear' => $token->getExpiryYear(),
            'cardScheme' => $token->getCardScheme(),
            'atc' => $token->getAtc(),
            'sessionKey' => $token->getSessionKey(), // encrypted — decrypted on device
            'emvKeys' => $token->getEncryptedEmvKeys(), // encrypted
            'tokenReferenceId' => $token->getTokenReferenceId(),
        ];
    }

    /**
     * Refresh session keys for continued NFC usage.
     * Called when the current session key expires or after each tap.
     */
    public function refreshSessionKey(HceToken $token): array
    {
        if ($token->getStatus() !== 'ACTIVE') {
            throw new \RuntimeException('Token is deactivated — re-provision required');
        }

        // Verify DPAN is still active at the issuer
        $dpanStatus = $this->cardIssuer->getDigitalCardStatus($token->getTokenReferenceId());
        if ($dpanStatus['status'] !== 'ACTIVE') {
            $token->setStatus('DEACTIVATED');
            $this->em->flush();
            throw new \RuntimeException('DPAN deactivated by issuer');
        }

        // Generate fresh EMV session keys
        $session = $this->cardIssuer->generateEmvSessionKeys(
            $token->getTokenReferenceId(),
            $token->getAtc(),
        );

        $token->setSessionKey($this->encryption->encrypt($session['sessionKey']));
        $token->setAtc($session['atc']);
        $token->setExpiresAt(new \DateTimeImmutable("+{$this->sessionKeyTtl} seconds"));

        $this->em->flush();

        return [
            'atc' => $session['atc'],
            'sessionKey' => $token->getSessionKey(),
            'expiresAt' => $token->getExpiresAt()->format('c'),
        ];
    }

    /**
     * Deactivate a single HCE token.
     */
    public function deactivateToken(HceToken $token): void
    {
        try {
            $this->cardIssuer->deactivateDigitalCard($token->getTokenReferenceId());
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to deactivate DPAN at issuer', ['error' => $e->getMessage()]);
        }

        $token->setStatus('DEACTIVATED');
        $this->em->flush();
    }

    /**
     * Deactivate all HCE tokens for a card (e.g., when card is blocked).
     */
    public function deactivateAllTokensForCard(Card $card): void
    {
        $tokens = $this->hceTokenRepo->findActiveByCard($card);
        foreach ($tokens as $token) {
            $this->deactivateToken($token);
        }
    }
}
