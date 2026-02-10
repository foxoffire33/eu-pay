<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\DirectDebitMandate;
use App\Entity\LinkedBankAccount;
use App\Entity\User;
use App\Repository\DirectDebitMandateRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * SEPA Direct Debit (SDD / Euro-incasso) management.
 *
 * Manages mandate lifecycle and initiates direct debits to fund the card issuer.
 * SEPA SDD Core: D+1 settlement, 8-week consumer refund right.
 */
class DirectDebitService
{
    public function __construct(
        private readonly OpenBankingInterface $openBanking,
        private readonly DirectDebitMandateRepository $mandateRepo,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        private readonly string $creditorId,
    ) {}

    /**
     * Create a SEPA Direct Debit mandate for a linked bank account.
     */
    public function createMandate(
        User $user,
        LinkedBankAccount $linkedAccount,
        int $maxAmountCents = 50000,
    ): DirectDebitMandate {
        // Revoke any existing active mandate
        $existing = $this->mandateRepo->findActiveByUser($user);
        if ($existing !== null) {
            $existing->revoke();
        }

        $mandate = new DirectDebitMandate();
        $mandate->setUser($user);
        $mandate->setLinkedBankAccount($linkedAccount);
        $mandate->setCreditorId($this->creditorId);
        $mandate->setMaxAmountCents($maxAmountCents);

        $this->em->persist($mandate);
        $this->em->flush();

        $this->logger->info('SDD mandate created', [
            'mandateRef' => $mandate->getMandateReference(),
            'maxAmount' => $maxAmountCents / 100,
        ]);

        return $mandate;
    }

    /**
     * Activate mandate after user signs consent.
     */
    public function activateMandate(DirectDebitMandate $mandate): DirectDebitMandate
    {
        $mandate->activate();
        $this->em->flush();

        $this->logger->info('SDD mandate activated', [
            'mandateRef' => $mandate->getMandateReference(),
        ]);

        return $mandate;
    }

    /**
     * Revoke an active mandate.
     */
    public function revokeMandate(DirectDebitMandate $mandate): void
    {
        $mandate->revoke();
        $this->em->flush();

        $this->logger->info('SDD mandate revoked', [
            'mandateRef' => $mandate->getMandateReference(),
        ]);
    }

    /**
     * Initiate a SEPA Direct Debit to pull funds from user's bank.
     *
     * Used to fund the card issuer after a card transaction.
     *
     * @return array{paymentId: string, status: string}
     */
    public function initiateDirectDebit(
        DirectDebitMandate $mandate,
        int $amountCents,
        string $reference,
    ): array {
        if (!$mandate->isActive()) {
            throw new \RuntimeException('SDD mandate is not active');
        }

        if ($amountCents > $mandate->getMaxAmountCents()) {
            throw new \RuntimeException(sprintf(
                'Amount %s exceeds mandate limit of %s',
                number_format($amountCents / 100, 2),
                number_format($mandate->getMaxAmountCents() / 100, 2),
            ));
        }

        $account = $mandate->getLinkedBankAccount();
        $amountEur = number_format($amountCents / 100, 2, '.', '');

        // Use SEPA transfer as SDD proxy (PSD2 PISP)
        $result = $this->openBanking->initiateSepaTransfer(
            debtorIban: $account->getExternalAccountId() ?? '',
            debtorName: 'Account Holder',
            creditorIban: $this->creditorId,
            creditorName: 'EU Pay',
            amountEur: $amountEur,
            reference: $reference,
        );

        $this->logger->info('SDD direct debit initiated', [
            'mandateRef' => $mandate->getMandateReference(),
            'amount' => $amountEur,
            'paymentId' => $result['paymentId'],
        ]);

        return [
            'paymentId' => $result['paymentId'],
            'status' => $result['status'],
        ];
    }

    /**
     * Get the active mandate for a user.
     */
    public function getActiveMandate(User $user): ?DirectDebitMandate
    {
        return $this->mandateRepo->findActiveByUser($user);
    }

    /**
     * Check if user has an active SDD mandate.
     */
    public function hasActiveMandate(User $user): bool
    {
        return $this->mandateRepo->findActiveByUser($user) !== null;
    }
}
