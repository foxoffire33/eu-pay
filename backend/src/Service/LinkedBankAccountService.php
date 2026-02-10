<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\LinkedBankAccount;
use App\Entity\User;
use App\Repository\LinkedBankAccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Orchestrates PSD2 AISP bank account linking.
 *
 * Flow: User enters IBAN → PSD2 consent created → SCA at bank → account linked.
 * Balance and transactions read in real-time via AISP consent.
 */
class LinkedBankAccountService
{
    public function __construct(
        private readonly OpenBankingInterface $openBanking,
        private readonly LinkedBankAccountRepository $accountRepo,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Link a new bank account via PSD2 AISP consent.
     *
     * @return array{accountId: string, authorisationUrl: string, validUntil: string}
     */
    public function linkAccount(
        User $user,
        string $iban,
        ?string $bic = null,
        ?string $label = null,
    ): array {
        $iban = strtoupper(str_replace(' ', '', $iban));
        $ibanCountryCode = substr($iban, 0, 2);
        $ibanLastFour = substr($iban, -4);

        // Look up bank name from registry
        $bankName = null;
        if ($bic !== null) {
            $bank = EuBankRegistry::findByBic($bic);
            $bankName = $bank['name'] ?? null;
        }

        // Create PSD2 AISP consent
        $consent = $this->openBanking->createAccountConsent($iban);

        $account = new LinkedBankAccount();
        $account->setUser($user);
        $account->setIbanCountryCode($ibanCountryCode);
        $account->setIbanLastFour($ibanLastFour);
        $account->setBankBic($bic);
        $account->setBankName($bankName);
        $account->setLabel($label);
        $account->setConsentId($consent['consentId']);
        $account->setAuthorisationUrl($consent['authorisationUrl']);
        $account->setConsentValidUntil(new \DateTimeImmutable($consent['validUntil']));
        $account->setExternalAccountId($iban);

        $this->em->persist($account);
        $this->em->flush();

        $this->logger->info('Bank account linking initiated', [
            'accountId' => $account->getId()->toRfc4122(),
            'country' => $ibanCountryCode,
            'bic' => $bic,
        ]);

        return [
            'accountId' => $account->getId()->toRfc4122(),
            'authorisationUrl' => $consent['authorisationUrl'],
            'validUntil' => $consent['validUntil'],
        ];
    }

    /**
     * Handle SCA callback — activate or fail the linked account.
     */
    public function handleConsentCallback(string $consentId, bool $success): LinkedBankAccount
    {
        $account = $this->accountRepo->findByConsentId($consentId);
        if ($account === null) {
            throw new \RuntimeException("No linked account found for consent: {$consentId}");
        }

        if ($success) {
            $account->activate($consentId, $account->getExternalAccountId() ?? '');
            $this->logger->info('Bank account linked', [
                'accountId' => $account->getId()->toRfc4122(),
            ]);
        } else {
            $account->markFailed();
            $this->logger->warning('Bank account linking failed', [
                'accountId' => $account->getId()->toRfc4122(),
            ]);
        }

        $this->em->flush();
        return $account;
    }

    /**
     * Get balance for a linked account via PSD2 AISP.
     */
    public function getBalance(LinkedBankAccount $account): array
    {
        if (!$account->isUsable()) {
            throw new \RuntimeException('Account consent expired or inactive');
        }

        return $this->openBanking->getAccountBalance(
            $account->getConsentId() ?? '',
            $account->getExternalAccountId() ?? '',
        );
    }

    /**
     * Get transactions for a linked account via PSD2 AISP.
     */
    public function getTransactions(LinkedBankAccount $account, string $dateFrom): array
    {
        if (!$account->isUsable()) {
            throw new \RuntimeException('Account consent expired or inactive');
        }

        return $this->openBanking->getAccountTransactions(
            $account->getConsentId() ?? '',
            $account->getExternalAccountId() ?? '',
            $dateFrom,
        );
    }

    /**
     * Get all non-revoked linked accounts for a user.
     *
     * @return LinkedBankAccount[]
     */
    public function getAccounts(User $user): array
    {
        return $this->accountRepo->findByUser($user);
    }

    /**
     * Unlink (revoke) a bank account.
     */
    public function unlinkAccount(LinkedBankAccount $account): void
    {
        $account->revoke();
        $this->em->flush();

        $this->logger->info('Bank account unlinked', [
            'accountId' => $account->getId()->toRfc4122(),
        ]);
    }

    /**
     * Refresh an expiring PSD2 consent.
     *
     * @return array{authorisationUrl: string, validUntil: string}
     */
    public function refreshConsent(LinkedBankAccount $account, string $iban): array
    {
        $consent = $this->openBanking->createAccountConsent($iban);

        $account->refreshConsent(
            $consent['consentId'],
            new \DateTimeImmutable($consent['validUntil']),
        );
        $account->setAuthorisationUrl($consent['authorisationUrl']);
        $this->em->flush();

        return [
            'authorisationUrl' => $consent['authorisationUrl'],
            'validUntil' => $consent['validUntil'],
        ];
    }

    /**
     * Check if the user's linked bank account has sufficient balance.
     */
    public function checkBalanceSufficient(LinkedBankAccount $account, int $amountCents): bool
    {
        try {
            $balances = $this->getBalance($account);
            foreach ($balances as $balance) {
                if (($balance['balanceType'] ?? '') === 'closingBooked') {
                    $available = (float) ($balance['balanceAmount']['amount'] ?? 0);
                    $required = $amountCents / 100;
                    return $available >= $required;
                }
            }
            // If no closingBooked balance found, check first available
            if (!empty($balances)) {
                $available = (float) ($balances[0]['balanceAmount']['amount'] ?? 0);
                $required = $amountCents / 100;
                return $available >= $required;
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Balance check failed', ['error' => $e->getMessage()]);
        }

        return false;
    }

    /**
     * Get the first active linked account for a user.
     */
    public function getActiveAccount(User $user): ?LinkedBankAccount
    {
        $accounts = $this->accountRepo->findActiveByUser($user);
        return $accounts[0] ?? null;
    }

    /**
     * Check if user has at least one active linked bank account.
     */
    public function hasActiveAccount(User $user): bool
    {
        return !empty($this->accountRepo->findActiveByUser($user));
    }
}
