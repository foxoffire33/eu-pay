<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\TransactionRepository;
use App\Service\Crypto\EnvelopeEncryptionService;
use App\Service\OpenBankingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Account management — PSD2 Open Banking.
 *
 * Accounts are created via PSD2 AISP consent. Balance and transactions
 * are read from the connected bank account via PSD2.
 */
#[Route('/api/account')]
#[IsGranted('ROLE_USER')]
class AccountController extends AbstractController
{
    public function __construct(
        private readonly OpenBankingService $banking,
        private readonly EntityManagerInterface $em,
        private readonly TransactionRepository $txRepo,
        private readonly EnvelopeEncryptionService $envelope,
    ) {}

    /**
     * Create / link a bank account via PSD2 AISP consent.
     * Returns an authorisation URL for the user to grant access at their bank.
     */
    #[Route('/create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $data = $request->toArray();
        $iban = $data['iban'] ?? '';

        if (empty($iban)) {
            return $this->json(['error' => 'IBAN is required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $consent = $this->banking->createAccountConsent($iban);

            $user->setExternalAccountId($iban);
            $this->em->flush();

            return $this->json([
                'consentId' => $consent['consentId'],
                'authorisationUrl' => $consent['authorisationUrl'],
                'validUntil' => $consent['validUntil'],
            ], Response::HTTP_CREATED);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_GATEWAY);
        }
    }

    /**
     * Get account balance via PSD2 AISP.
     */
    #[Route('/balance', methods: ['GET'])]
    public function balance(): JsonResponse
    {
        $user = $this->getUser();
        $accountId = $user->getExternalAccountId();
        if ($accountId === null) {
            return $this->json(['error' => 'No bank account linked'], Response::HTTP_NOT_FOUND);
        }

        try {
            $balances = $this->banking->getAccountBalance(
                $user->getPsd2ConsentId() ?? '',
                $accountId,
            );

            return $this->json([
                'balances' => $balances,
                'account_id' => $accountId,
            ]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_GATEWAY);
        }
    }

    /**
     * Get transaction history via PSD2 AISP.
     */
    #[Route('/transactions', methods: ['GET'])]
    public function transactions(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $accountId = $user->getExternalAccountId();
        if ($accountId === null) {
            return $this->json(['error' => 'No bank account linked'], Response::HTTP_NOT_FOUND);
        }

        $dateFrom = $request->query->get('from', (new \DateTime('-30 days'))->format('Y-m-d'));

        try {
            $transactions = $this->banking->getAccountTransactions(
                $user->getPsd2ConsentId() ?? '',
                $accountId,
                $dateFrom,
            );

            return $this->json(['transactions' => $transactions]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_GATEWAY);
        }
    }
}
