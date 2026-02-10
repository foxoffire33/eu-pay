<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\P2PTransfer;
use App\Entity\User;
use App\Repository\P2PTransferRepository;
use App\Repository\UserRepository;
use App\Service\Crypto\BlindIndexService;
use App\Service\Crypto\EnvelopeEncryptionService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * P2P transfer orchestration — PSD2 Open Banking only.
 *
 * Internal (EU Pay → EU Pay):
 *  - Instant book transfer via PSD2 PISP
 *  - Recipient looked up by email blind index
 *  - Zero fees, instant settlement
 *
 * External (EU Pay → any EU/EEA IBAN):
 *  - SEPA Credit Transfer via PSD2 PISP
 *  - 1-2 business day settlement (SEPA Instant: <10s where supported)
 *  - All EU/EEA banks supported — PSD2 mandatory
 */
class P2PTransferService
{
    private const MIN_AMOUNT_CENTS = 1;
    private const MAX_AMOUNT_CENTS = 1_500_000;
    private const DAILY_LIMIT_CENTS = 5_000_000;

    public function __construct(
        private readonly OpenBankingService $banking,
        private readonly EnvelopeEncryptionService $encryption,
        private readonly BlindIndexService $blindIndex,
        private readonly UserRepository $userRepository,
        private readonly P2PTransferRepository $p2pRepository,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Send money to another EU Pay user (by email).
     * Instant book transfer — no SEPA, no bank fees.
     */
    public function sendToUser(
        User $sender,
        string $recipientEmail,
        int $amountCents,
        ?string $message = null,
    ): array {
        $this->validateAmount($amountCents);
        $this->checkDailyLimit($sender, $amountCents);

        $emailIndex = $this->blindIndex->compute($recipientEmail);
        $recipient = $this->userRepository->findByEmailIndex($emailIndex);

        if ($recipient === null) {
            throw new \InvalidArgumentException('Recipient not found. They must have an EU Pay account.');
        }
        if ($recipient->getId()->equals($sender->getId())) {
            throw new \InvalidArgumentException('Cannot send money to yourself.');
        }

        $transfer = new P2PTransfer();
        $transfer->setSender($sender);
        $transfer->setRecipient($recipient);
        $transfer->setType(P2PTransfer::TYPE_INTERNAL);
        $transfer->setAmountCents($amountCents);
        $transfer->setRecipientEmailIndex($emailIndex);

        if ($message !== null && $recipient->getPublicKey()) {
            $transfer->setEncryptedMessage(
                $this->encryption->encrypt($message, $recipient->getPublicKey())
            );
        }

        try {
            $result = $this->banking->initiateSepaTransfer(
                debtorIban: $sender->getExternalAccountId() ?? '',
                debtorName: 'EU Pay User',
                creditorIban: $recipient->getExternalAccountId() ?? '',
                creditorName: 'EU Pay User',
                amountEur: $transfer->getAmountEur(),
                reference: $transfer->getReference(),
            );

            $transfer->setExternalDebitTransactionId($result['paymentId']);
            $transfer->markCompleted();
        } catch (\Throwable $e) {
            $transfer->markFailed($e->getMessage());
            $this->logger->error('P2P internal transfer failed', [
                'transferId' => $transfer->getId()->toRfc4122(),
                'error' => $e->getMessage(),
            ]);
        }

        $this->em->persist($transfer);
        $this->em->flush();

        return [
            'transferId' => $transfer->getId()->toRfc4122(),
            'status' => $transfer->getStatus(),
            'type' => $transfer->getType(),
        ];
    }

    /**
     * Send money to any EU/EEA bank account via SEPA Credit Transfer.
     * All EU banks supported — PSD2 is mandatory across EU/EEA.
     */
    public function sendToIban(
        User $sender,
        string $recipientIban,
        string $recipientName,
        int $amountCents,
        ?string $recipientBic = null,
        ?string $message = null,
    ): array {
        $this->validateAmount($amountCents);
        $this->validateIban($recipientIban);
        $this->checkDailyLimit($sender, $amountCents);

        $transfer = new P2PTransfer();
        $transfer->setSender($sender);
        $transfer->setType(P2PTransfer::TYPE_EXTERNAL);
        $transfer->setAmountCents($amountCents);
        $transfer->setRecipientBic($recipientBic);

        if ($sender->getPublicKey()) {
            $transfer->setEncryptedRecipientIban(
                $this->encryption->encrypt($recipientIban, $sender->getPublicKey())
            );
            $transfer->setEncryptedRecipientName(
                $this->encryption->encrypt($recipientName, $sender->getPublicKey())
            );
        }

        $transfer->setRecipientIbanIndex($this->blindIndex->compute($recipientIban));

        if ($message !== null && $sender->getPublicKey()) {
            $transfer->setEncryptedMessage(
                $this->encryption->encrypt($message, $sender->getPublicKey())
            );
        }

        try {
            $result = $this->banking->initiateSepaTransfer(
                debtorIban: $sender->getExternalAccountId() ?? '',
                debtorName: 'EU Pay User',
                creditorIban: $recipientIban,
                creditorName: $recipientName,
                amountEur: $transfer->getAmountEur(),
                reference: $transfer->getReference(),
            );

            $transfer->setExternalDebitTransactionId($result['paymentId']);
            $transfer->setExternalPaymentId($result['paymentId']);
            $transfer->markPending();
        } catch (\Throwable $e) {
            $transfer->markFailed($e->getMessage());
            $this->logger->error('P2P external transfer failed', [
                'transferId' => $transfer->getId()->toRfc4122(),
                'error' => $e->getMessage(),
            ]);
        }

        $this->em->persist($transfer);
        $this->em->flush();

        return [
            'transferId' => $transfer->getId()->toRfc4122(),
            'status' => $transfer->getStatus(),
            'type' => $transfer->getType(),
        ];
    }

    public function getHistory(User $user, int $limit = 20): array
    {
        $sent = $this->p2pRepository->findSentByUser($user, $limit);
        $received = $this->p2pRepository->findReceivedByUser($user, $limit);
        $all = array_merge($sent, $received);
        usort($all, fn(P2PTransfer $a, P2PTransfer $b) => $b->getCreatedAt() <=> $a->getCreatedAt());
        return array_slice($all, 0, $limit);
    }

    public function getSupportedBanks(): array
    {
        return EuBankRegistry::getAll();
    }

    public function getBanksByCountry(string $countryCode): array
    {
        return EuBankRegistry::getByCountry($countryCode);
    }

    private function validateAmount(int $cents): void
    {
        if ($cents < self::MIN_AMOUNT_CENTS) {
            throw new \InvalidArgumentException('Minimum transfer is €0.01');
        }
        if ($cents > self::MAX_AMOUNT_CENTS) {
            throw new \InvalidArgumentException(
                sprintf('Maximum transfer is €%.2f', self::MAX_AMOUNT_CENTS / 100)
            );
        }
    }

    private function validateIban(string $iban): void
    {
        $iban = strtoupper(str_replace(' ', '', $iban));
        if (strlen($iban) < 15 || strlen($iban) > 34) {
            throw new \InvalidArgumentException('Invalid IBAN length');
        }

        $countryCode = substr($iban, 0, 2);
        $supported = EuBankRegistry::getSupportedCountries();
        if (!in_array($countryCode, $supported, true)) {
            throw new \InvalidArgumentException(
                "IBAN country {$countryCode} is not in the EU/EEA."
            );
        }

        $rearranged = substr($iban, 4) . substr($iban, 0, 4);
        $numeric = '';
        for ($i = 0; $i < strlen($rearranged); $i++) {
            $c = $rearranged[$i];
            $numeric .= ctype_alpha($c) ? (string)(ord($c) - 55) : $c;
        }
        if (bcmod($numeric, '97') !== '1') {
            throw new \InvalidArgumentException('Invalid IBAN checksum');
        }
    }

    private function checkDailyLimit(User $user, int $newAmountCents): void
    {
        $today = new \DateTimeImmutable('today');
        $todayTransfers = $this->p2pRepository->findSentByUser($user, 200);

        $todayTotal = 0;
        foreach ($todayTransfers as $transfer) {
            if ($transfer->getCreatedAt() >= $today && $transfer->getStatus() !== P2PTransfer::STATUS_FAILED) {
                $todayTotal += $transfer->getAmountCents();
            }
        }

        if ($todayTotal + $newAmountCents > self::DAILY_LIMIT_CENTS) {
            throw new \InvalidArgumentException(
                sprintf('Daily transfer limit is €%.2f', self::DAILY_LIMIT_CENTS / 100)
            );
        }
    }
}
