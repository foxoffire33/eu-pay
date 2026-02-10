<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Card;
use App\Entity\User;
use App\Repository\CardRepository;
use App\Service\CardIssuing\CardIssuerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Card management — virtual debit card lifecycle.
 *
 * Uses CardIssuerInterface (Marqeta/Adyen/Stripe) to issue Visa/Mastercard.
 * This is SEPARATE from PSD2 — PSD2 cannot issue cards.
 *
 * Architecture:
 *   PSD2 OpenBankingInterface → account access, top-up, P2P (AISP/PISP)
 *   CardIssuerInterface       → issue cards, NFC tokenization, tap-to-pay
 *
 * Before issuing or transacting:
 *   1. Check user has active linked bank account
 *   2. Check user has active SDD mandate (Euro-incasso)
 *   3. Verify sufficient balance via PSD2 AISP
 *   4. After transaction: auto-debit via SDD to fund card issuer
 */
class CardService
{
    public function __construct(
        private readonly CardIssuerInterface $cardIssuer,
        private readonly LinkedBankAccountService $bankAccountService,
        private readonly DirectDebitService $directDebitService,
        private readonly EntityManagerInterface $em,
        private readonly CardRepository $cardRepo,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Issue a new virtual debit card via the card issuer (Marqeta/Adyen).
     * The cardholder name is ephemeral — NOT stored on our backend.
     *
     * Prerequisites: active linked bank account + active SDD mandate.
     */
    public function createVirtualCard(User $user, string $cardholderName): Card
    {
        // Verify user has active bank account and mandate
        $activeAccount = $this->bankAccountService->getActiveAccount($user);
        if ($activeAccount === null) {
            throw new \RuntimeException('A linked bank account is required before creating a card.');
        }

        if (!$this->directDebitService->hasActiveMandate($user)) {
            throw new \RuntimeException('An active Euro-incasso mandate is required before creating a card.');
        }

        $issuedCard = $this->cardIssuer->createVirtualCard(
            userId: $user->getId()->toRfc4122(),
            cardholderName: $cardholderName,
        );

        $card = new Card();
        $card->setUser($user);
        $card->setExternalCardId($issuedCard['cardId']);
        $card->setExternalAccountId($user->getExternalAccountId());
        $card->setType('VIRTUAL');
        $card->setScheme($issuedCard['scheme'] ?? 'VISA');
        $card->setStatus($issuedCard['status'] ?? 'ACTIVE');
        $card->setLastFourDigits($issuedCard['last4']);
        $card->setExpiryDate(sprintf('%02d/%d', $issuedCard['expiryMonth'], $issuedCard['expiryYear']));

        $this->em->persist($card);
        $this->em->flush();

        $this->logger->info('Virtual card issued', [
            'cardId' => $card->getId()->toRfc4122(),
            'last4' => $issuedCard['last4'],
            'scheme' => $issuedCard['scheme'],
        ]);

        return $card;
    }

    public function activateCard(Card $card, string $verificationCode): Card
    {
        $this->cardIssuer->activateCard($card->getExternalCardId());
        $card->setStatus('ACTIVE');
        $this->em->flush();
        return $card;
    }

    public function blockCard(Card $card): Card
    {
        $this->cardIssuer->blockCard($card->getExternalCardId());
        $card->setStatus('BLOCKED');
        $this->em->flush();
        return $card;
    }

    public function unblockCard(Card $card): Card
    {
        $this->cardIssuer->unblockCard($card->getExternalCardId());
        $card->setStatus('ACTIVE');
        $this->em->flush();
        return $card;
    }

    /**
     * Load funds onto card after a PSD2 top-up completes.
     * Bridges PSD2 (funding) with card issuer (spending).
     */
    public function loadFunds(Card $card, int $amountCents): array
    {
        return $this->cardIssuer->loadFunds(
            $card->getExternalCardId(),
            $amountCents,
        );
    }

    public function getBalance(Card $card): array
    {
        return $this->cardIssuer->getCardBalance($card->getExternalCardId());
    }

    public function getUserCards(User $user): array
    {
        return $this->cardRepo->findByUser($user);
    }

    /**
     * Pre-transaction check: verify balance + mandate before card payment.
     *
     * @return array{allowed: bool, reason: ?string}
     */
    public function preTransactionCheck(User $user, int $amountCents): array
    {
        $activeAccount = $this->bankAccountService->getActiveAccount($user);
        if ($activeAccount === null) {
            return ['allowed' => false, 'reason' => 'No active linked bank account'];
        }

        if (!$this->directDebitService->hasActiveMandate($user)) {
            return ['allowed' => false, 'reason' => 'No active Euro-incasso mandate'];
        }

        if (!$this->bankAccountService->checkBalanceSufficient($activeAccount, $amountCents)) {
            return ['allowed' => false, 'reason' => 'Insufficient balance on linked bank account'];
        }

        return ['allowed' => true, 'reason' => null];
    }

    /**
     * Auto-fund card issuer via SEPA Direct Debit after a card transaction.
     */
    public function autoFundAfterTransaction(User $user, int $amountCents, string $reference): void
    {
        $mandate = $this->directDebitService->getActiveMandate($user);
        if ($mandate === null) {
            $this->logger->warning('No active mandate for auto-funding', [
                'userId' => $user->getId()->toRfc4122(),
            ]);
            return;
        }

        try {
            $this->directDebitService->initiateDirectDebit($mandate, $amountCents, $reference);
        } catch (\Throwable $e) {
            $this->logger->error('Auto-funding failed', [
                'userId' => $user->getId()->toRfc4122(),
                'amount' => $amountCents,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function syncCardStatus(string $externalCardId): Card
    {
        $card = $this->cardRepo->findByExternalCardId($externalCardId);
        if ($card === null) {
            throw new \RuntimeException("Card not found: {$externalCardId}");
        }

        $issuerCard = $this->cardIssuer->getCard($externalCardId);
        $card->setStatus($issuerCard['status']);
        $this->em->flush();

        return $card;
    }
}
