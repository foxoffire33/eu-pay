<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\LinkedBankAccount;
use App\Entity\User;
use App\Repository\CardRepository;
use App\Repository\LinkedBankAccountRepository;
use App\Service\DirectDebitService;
use App\Service\LinkedBankAccountService;
use App\Service\OpenBankingInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Bank account management — PSD2 AISP linking + SEPA Direct Debit mandates.
 *
 * Accounts are linked via PSD2 AISP consent. Balance and transactions
 * are read in real-time from the connected bank.
 * Euro-incasso (SDD) mandates authorize auto-funding of the virtual card.
 */
#[Route('/api/account')]
#[IsGranted('ROLE_USER')]
class AccountController extends AbstractController
{
    public function __construct(
        private readonly LinkedBankAccountService $accountService,
        private readonly DirectDebitService $directDebitService,
        private readonly LinkedBankAccountRepository $accountRepo,
        private readonly CardRepository $cardRepo,
        private readonly OpenBankingInterface $banking,
    ) {}

    // ── Bank Account Linking ──────────────────────────────

    /**
     * Link a new bank account via PSD2 AISP.
     * Returns authorisation URL for SCA redirect at user's bank.
     */
    #[Route('/link', methods: ['POST'])]
    public function link(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = $request->toArray();
        $iban = $data['iban'] ?? '';

        if (empty($iban)) {
            return $this->json(['error' => 'IBAN is required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = $this->accountService->linkAccount(
                $user,
                $iban,
                $data['bic'] ?? null,
                $data['label'] ?? null,
            );

            return $this->json($result, Response::HTTP_CREATED);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_GATEWAY);
        }
    }

    /**
     * Handle SCA callback after bank authorisation.
     */
    #[Route('/link/callback', methods: ['POST'])]
    public function linkCallback(Request $request): JsonResponse
    {
        $data = $request->toArray();
        $consentId = $data['consent_id'] ?? '';
        $success = (bool) ($data['success'] ?? false);

        if (empty($consentId)) {
            return $this->json(['error' => 'consent_id is required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $account = $this->accountService->handleConsentCallback($consentId, $success);

            return $this->json([
                'accountId' => $account->getId()->toRfc4122(),
                'status' => $account->getStatus(),
                'bank_name' => $account->getBankName(),
            ]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * List all linked bank accounts.
     */
    #[Route('/linked', methods: ['GET'])]
    public function linked(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $accounts = $this->accountService->getAccounts($user);

        return $this->json([
            'accounts' => array_map(fn (LinkedBankAccount $a) => [
                'id' => $a->getId()->toRfc4122(),
                'bank_name' => $a->getBankName(),
                'bank_bic' => $a->getBankBic(),
                'iban_last_four' => $a->getIbanLastFour(),
                'iban_country' => $a->getIbanCountryCode(),
                'status' => $a->getStatus(),
                'consent_valid_until' => $a->getConsentValidUntil()?->format('c'),
                'needs_refresh' => $a->needsConsentRefresh(),
                'label' => $a->getLabel(),
                'created_at' => $a->getCreatedAt()->format('c'),
            ], $accounts),
        ]);
    }

    /**
     * Get balance for a specific linked account.
     */
    #[Route('/{id}/balance', methods: ['GET'])]
    public function balance(string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $account = $this->findOwnedAccount($user, $id);

        if ($account === null) {
            return $this->json(['error' => 'Account not found'], Response::HTTP_NOT_FOUND);
        }

        if (!$account->isUsable()) {
            return $this->json(['error' => 'Consent expired — please refresh'], Response::HTTP_GONE);
        }

        try {
            $balances = $this->accountService->getBalance($account);

            return $this->json([
                'balances' => $balances,
                'account_id' => $id,
                'bank_name' => $account->getBankName(),
            ]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_GATEWAY);
        }
    }

    /**
     * Get transactions for a specific linked account.
     */
    #[Route('/{id}/transactions', methods: ['GET'])]
    public function transactions(string $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $account = $this->findOwnedAccount($user, $id);

        if ($account === null) {
            return $this->json(['error' => 'Account not found'], Response::HTTP_NOT_FOUND);
        }

        if (!$account->isUsable()) {
            return $this->json(['error' => 'Consent expired — please refresh'], Response::HTTP_GONE);
        }

        $dateFrom = $request->query->get('from', (new \DateTime('-30 days'))->format('Y-m-d'));

        try {
            $transactions = $this->accountService->getTransactions($account, $dateFrom);

            return $this->json([
                'transactions' => $transactions,
                'account_id' => $id,
            ]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_GATEWAY);
        }
    }

    /**
     * Unlink a bank account.
     */
    #[Route('/{id}', methods: ['DELETE'])]
    public function unlink(string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $account = $this->findOwnedAccount($user, $id);

        if ($account === null) {
            return $this->json(['error' => 'Account not found'], Response::HTTP_NOT_FOUND);
        }

        $this->accountService->unlinkAccount($account);

        return $this->json(['status' => 'revoked']);
    }

    /**
     * Refresh an expiring PSD2 consent.
     */
    #[Route('/{id}/refresh', methods: ['POST'])]
    public function refreshConsent(string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $account = $this->findOwnedAccount($user, $id);

        if ($account === null) {
            return $this->json(['error' => 'Account not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $result = $this->accountService->refreshConsent(
                $account,
                $account->getExternalAccountId() ?? '',
            );

            return $this->json($result);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_GATEWAY);
        }
    }

    /**
     * List supported EU/EEA banks.
     */
    #[Route('/banks', methods: ['GET'])]
    public function banks(Request $request): JsonResponse
    {
        $country = $request->query->get('country');

        if ($country !== null) {
            $banks = $this->banking->getBanksByCountry($country);
        } else {
            $banks = $this->banking->getSupportedBanks();
        }

        return $this->json([
            'banks' => $banks,
            'countries' => $this->banking->getSupportedCountries(),
            'total' => count($banks),
        ]);
    }

    // ── SEPA Direct Debit Mandate ────────────────────────

    /**
     * Create a SEPA Direct Debit mandate for the user's linked bank account.
     */
    #[Route('/mandate', methods: ['POST'])]
    public function createMandate(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = $request->toArray();
        $accountId = $data['account_id'] ?? '';

        $account = $this->findOwnedAccount($user, $accountId);
        if ($account === null || !$account->isUsable()) {
            return $this->json(['error' => 'Active linked bank account required'], Response::HTTP_BAD_REQUEST);
        }

        $maxAmountCents = (int) ($data['max_amount_cents'] ?? 50000);

        $mandate = $this->directDebitService->createMandate($user, $account, $maxAmountCents);

        return $this->json([
            'id' => $mandate->getId()->toRfc4122(),
            'mandate_reference' => $mandate->getMandateReference(),
            'status' => $mandate->getStatus(),
            'max_amount_cents' => $mandate->getMaxAmountCents(),
            'bank_name' => $account->getBankName(),
            'iban_last_four' => $account->getIbanLastFour(),
        ], Response::HTTP_CREATED);
    }

    /**
     * Activate a pending SDD mandate after user signs consent.
     */
    #[Route('/mandate/activate', methods: ['POST'])]
    public function activateMandate(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $mandate = $this->directDebitService->getActiveMandate($user);

        // Also activate pending mandates
        if ($mandate === null) {
            $mandates = $user->getDirectDebitMandates()->filter(
                fn ($m) => $m->getStatus() === 'pending'
            );
            $mandate = $mandates->first() ?: null;
        }

        if ($mandate === null) {
            return $this->json(['error' => 'No pending mandate found'], Response::HTTP_NOT_FOUND);
        }

        $mandate = $this->directDebitService->activateMandate($mandate);

        return $this->json([
            'id' => $mandate->getId()->toRfc4122(),
            'status' => $mandate->getStatus(),
            'signed_at' => $mandate->getSignedAt()?->format('c'),
        ]);
    }

    /**
     * Revoke the active SDD mandate.
     */
    #[Route('/mandate', methods: ['DELETE'])]
    public function revokeMandate(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $mandate = $this->directDebitService->getActiveMandate($user);

        if ($mandate === null) {
            return $this->json(['error' => 'No active mandate found'], Response::HTTP_NOT_FOUND);
        }

        $this->directDebitService->revokeMandate($mandate);

        return $this->json(['status' => 'revoked']);
    }

    /**
     * Get the user's active SDD mandate status.
     */
    #[Route('/mandate', methods: ['GET'])]
    public function getMandate(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $mandate = $this->directDebitService->getActiveMandate($user);

        if ($mandate === null) {
            return $this->json(['mandate' => null]);
        }

        return $this->json([
            'mandate' => [
                'id' => $mandate->getId()->toRfc4122(),
                'mandate_reference' => $mandate->getMandateReference(),
                'status' => $mandate->getStatus(),
                'max_amount_cents' => $mandate->getMaxAmountCents(),
                'signed_at' => $mandate->getSignedAt()?->format('c'),
                'bank_name' => $mandate->getLinkedBankAccount()->getBankName(),
                'iban_last_four' => $mandate->getLinkedBankAccount()->getIbanLastFour(),
            ],
        ]);
    }

    // ── Onboarding Status ────────────────────────────────

    /**
     * Check all 3 prerequisites before the user can use the app:
     * 1. Bank account linked
     * 2. Virtual card issued
     * 3. Euro-incasso mandate active
     */
    #[Route('/onboarding-status', methods: ['GET'])]
    public function onboardingStatus(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $bankLinked = $this->accountService->hasActiveAccount($user);
        $cardIssued = !empty($this->cardRepo->findByUser($user));
        $mandateActive = $this->directDebitService->hasActiveMandate($user);

        return $this->json([
            'bank_linked' => $bankLinked,
            'card_issued' => $cardIssued,
            'mandate_active' => $mandateActive,
            'ready' => $bankLinked && $cardIssued && $mandateActive,
        ]);
    }

    // ── Helpers ──────────────────────────────────────────

    private function findOwnedAccount(User $user, string $id): ?LinkedBankAccount
    {
        if (empty($id)) {
            return null;
        }

        $account = $this->accountRepo->find($id);
        if ($account === null || $account->getUser()->getId()->toRfc4122() !== $user->getId()->toRfc4122()) {
            return null;
        }

        return $account;
    }
}
