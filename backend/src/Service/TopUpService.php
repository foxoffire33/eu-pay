<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\TopUp;
use App\Entity\User;
use App\Repository\TopUpRepository;
use App\Service\Crypto\EnvelopeEncryptionService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Top-up orchestration: initiate → redirect to bank → SCA → callback → credit account.
 *
 * Supported methods:
 *  - iDEAL  (Dutch instant, via PSD2 PISP — all major Dutch banks incl. Rabobank)
 *  - SEPA Credit Transfer (any EU IBAN, 1-2 business day settlement)
 *
 * Minimum: €1.00 · Maximum: €10,000.00 per transaction (AML threshold).
 */
class TopUpService
{
    private const MIN_AMOUNT_CENTS = 100;         // €1.00
    private const MAX_AMOUNT_CENTS = 1_000_000;   // €10,000.00
    private const DAILY_LIMIT_CENTS = 5_000_000;  // €50,000.00 daily

    public function __construct(
        private readonly OpenBankingService $openBanking,
        private readonly EnvelopeEncryptionService $encryption,
        private readonly TopUpRepository $topUpRepository,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        private readonly string $creditorIban,
        private readonly string $creditorName,
    ) {
    }

    /**
     * Initiate an iDEAL top-up (Dutch banks: Rabobank, ING, ABN AMRO, etc.)
     *
     * @return array{topUpId: string, authorisationUrl: string, reference: string}
     */
    public function initiateIdeal(
        User $user,
        int $amountCents,
        ?string $sourceBic = null,
    ): array {
        $this->validateAmount($amountCents);
        $this->checkDailyLimit($user, $amountCents);

        $topUp = new TopUp();
        $topUp->setUser($user);
        $topUp->setAmountCents($amountCents);
        $topUp->setMethod(TopUp::METHOD_IDEAL);
        $topUp->setSourceBic($sourceBic);

        // Initiate PSD2 payment via Rabobank API
        $result = $this->openBanking->initiateIdealPayment(
            creditorIban: $this->creditorIban,
            creditorName: $this->creditorName,
            amountEur: $topUp->getAmountEur(),
            reference: $topUp->getReference(),
            debtorBic: $sourceBic,
        );

        $topUp->setExternalPaymentId($result['paymentId']);
        $topUp->setAuthorisationUrl($result['authorisationUrl']);

        $this->em->persist($topUp);
        $this->em->flush();

        $this->logger->info('iDEAL top-up initiated', [
            'topUpId' => $topUp->getId()->toRfc4122(),
            'amount' => $topUp->getAmountEur(),
            'bic' => $sourceBic,
        ]);

        return [
            'topUpId' => $topUp->getId()->toRfc4122(),
            'authorisationUrl' => $result['authorisationUrl'],
            'reference' => $topUp->getReference(),
        ];
    }

    /**
     * Initiate a SEPA Credit Transfer top-up (any EU bank).
     *
     * @return array{topUpId: string, authorisationUrl: string, reference: string}
     */
    public function initiateSepa(
        User $user,
        int $amountCents,
        string $sourceIban,
        string $sourceName,
    ): array {
        $this->validateAmount($amountCents);
        $this->checkDailyLimit($user, $amountCents);

        $topUp = new TopUp();
        $topUp->setUser($user);
        $topUp->setAmountCents($amountCents);
        $topUp->setMethod(TopUp::METHOD_SEPA);

        // Encrypt source IBAN (zero-knowledge)
        $encryptedIban = $this->encryption->encrypt($sourceIban, $user->getPublicKey());
        $topUp->setEncryptedSourceIban($encryptedIban);

        $result = $this->openBanking->initiateSepaTransfer(
            debtorIban: $sourceIban,
            debtorName: $sourceName,
            creditorIban: $this->creditorIban,
            creditorName: $this->creditorName,
            amountEur: $topUp->getAmountEur(),
            reference: $topUp->getReference(),
        );

        $topUp->setExternalPaymentId($result['paymentId']);
        $topUp->setAuthorisationUrl($result['authorisationUrl']);

        $this->em->persist($topUp);
        $this->em->flush();

        $this->logger->info('SEPA top-up initiated', [
            'topUpId' => $topUp->getId()->toRfc4122(),
            'amount' => $topUp->getAmountEur(),
        ]);

        return [
            'topUpId' => $topUp->getId()->toRfc4122(),
            'authorisationUrl' => $result['authorisationUrl'],
            'reference' => $topUp->getReference(),
        ];
    }

    /**
     * Handle callback after SCA redirect from bank.
     */
    public function handleCallback(string $externalPaymentId, bool $success): TopUp
    {
        $topUp = $this->topUpRepository->findByExternalPaymentId($externalPaymentId);
        if ($topUp === null) {
            throw new \InvalidArgumentException("Unknown payment: {$externalPaymentId}");
        }

        if ($topUp->isTerminal()) {
            return $topUp;
        }

        if (!$success) {
            $topUp->markCancelled();
            $this->em->flush();
            return $topUp;
        }

        // Verify payment status with bank
        $paymentProduct = $topUp->getMethod() === TopUp::METHOD_IDEAL
            ? 'ideal-payments'
            : 'sepa-credit-transfers';

        $status = $this->openBanking->getPaymentStatus($paymentProduct, $externalPaymentId);

        match ($status['status']) {
            'ACSC', 'ACSP', 'ACCC' => $topUp->markPending(),
            'RJCT' => $topUp->markFailed('Payment rejected by bank'),
            'CANC' => $topUp->markCancelled(),
            default => null, // keep current status
        };

        $this->em->flush();

        $this->logger->info('Top-up callback processed', [
            'topUpId' => $topUp->getId()->toRfc4122(),
            'bankStatus' => $status['status'],
            'appStatus' => $topUp->getStatus(),
        ]);

        return $topUp;
    }

    /**
     * Called by webhook when bank confirms incoming funds.
     */
    public function confirmSettlement(string $externalPaymentId, string $externalTransactionId): TopUp
    {
        $topUp = $this->topUpRepository->findByExternalPaymentId($externalPaymentId);
        if ($topUp === null) {
            throw new \InvalidArgumentException("Unknown payment: {$externalPaymentId}");
        }

        $topUp->markCompleted($externalTransactionId);
        $this->em->flush();

        $this->logger->info('Top-up settled', [
            'topUpId' => $topUp->getId()->toRfc4122(),
            'amount' => $topUp->getAmountEur(),
            'externalTransactionId' => $externalTransactionId,
        ]);

        return $topUp;
    }

    /** Get user's top-up history */
    public function getHistory(User $user, int $limit = 20): array
    {
        return $this->topUpRepository->findByUser($user, $limit);
    }

    /** Get supported banks for iDEAL */
    public function getSupportedBanks(): array
    {
        return $this->openBanking->getSupportedBanks();
    }

    // ── Validation ──────────────────────────────────────

    private function validateAmount(int $cents): void
    {
        if ($cents < self::MIN_AMOUNT_CENTS) {
            throw new \InvalidArgumentException(
                sprintf('Minimum top-up is €%.2f', self::MIN_AMOUNT_CENTS / 100)
            );
        }
        if ($cents > self::MAX_AMOUNT_CENTS) {
            throw new \InvalidArgumentException(
                sprintf('Maximum top-up is €%.2f', self::MAX_AMOUNT_CENTS / 100)
            );
        }
    }

    private function checkDailyLimit(User $user, int $newAmountCents): void
    {
        $today = new \DateTimeImmutable('today');
        $todaysTopUps = $this->topUpRepository->findByUser($user, 100);

        $todayTotal = 0;
        foreach ($todaysTopUps as $topUp) {
            if ($topUp->getCreatedAt() >= $today && !$topUp->isTerminal()) {
                $todayTotal += $topUp->getAmountCents();
            }
        }

        if ($todayTotal + $newAmountCents > self::DAILY_LIMIT_CENTS) {
            throw new \InvalidArgumentException(
                sprintf('Daily top-up limit is €%.2f', self::DAILY_LIMIT_CENTS / 100)
            );
        }
    }
}
