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
 *   PSD2 OpenBankingService → account access, top-up, P2P (AISP/PISP)
 *   CardIssuerInterface     → issue cards, NFC tokenization, tap-to-pay
 */
class CardService
{
    public function __construct(
        private readonly CardIssuerInterface $cardIssuer,
        private readonly EntityManagerInterface $em,
        private readonly CardRepository $cardRepo,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Issue a new virtual debit card via the card issuer (Marqeta/Adyen).
     * The cardholder name is ephemeral — NOT stored on our backend.
     */
    public function createVirtualCard(User $user, string $cardholderName): Card
    {
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
