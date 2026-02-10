<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\TopUpService;
use App\Service\EuBankRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Top-up: fund your EU Pay account from any EU/EEA bank.
 *
 * Methods:
 *  - iDEAL (instant, Netherlands — works with Rabobank, ING, ABN AMRO, etc.)
 *  - SEPA Credit Transfer (any EU/EEA IBAN, 1-2 business days)
 *
 * Flow:
 *  1. POST /api/topup/ideal or /api/topup/sepa → get authorisationUrl
 *  2. Redirect user to bank for SCA (Strong Customer Authentication)
 *  3. Bank redirects back to /api/topup/callback → status updated
 *  4. Webhook confirms settlement → funds credited to bank account
 */
#[Route('/api/topup')]
#[IsGranted('ROLE_USER')]
class TopUpController extends AbstractController
{
    public function __construct(
        private readonly TopUpService $topUpService,
    ) {
    }

    /**
     * Initiate iDEAL top-up (Dutch instant bank transfer).
     * Supported by all Dutch banks + select EU banks.
     */
    #[Route('/ideal', methods: ['POST'])]
    public function initiateIdeal(Request $request): JsonResponse
    {
        $data = $request->toArray();
        $amountCents = (int) ($data['amount_cents'] ?? 0);
        $sourceBic = $data['source_bic'] ?? null;

        try {
            $result = $this->topUpService->initiateIdeal(
                $this->getUser(),
                $amountCents,
                $sourceBic,
            );

            return $this->json($result, Response::HTTP_CREATED);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Initiate SEPA Credit Transfer top-up (any EU/EEA bank).
     * Works with every PSD2-compliant bank in the EU.
     */
    #[Route('/sepa', methods: ['POST'])]
    public function initiateSepa(Request $request): JsonResponse
    {
        $data = $request->toArray();
        $amountCents = (int) ($data['amount_cents'] ?? 0);
        $sourceIban = $data['source_iban'] ?? '';
        $sourceName = $data['source_name'] ?? '';

        try {
            $result = $this->topUpService->initiateSepa(
                $this->getUser(),
                $amountCents,
                $sourceIban,
                $sourceName,
            );

            return $this->json($result, Response::HTTP_CREATED);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Callback after SCA redirect from bank.
     * The bank redirects the user's phone browser back here.
     */
    #[Route('/callback', methods: ['GET'])]
    public function callback(Request $request): JsonResponse
    {
        $paymentId = $request->query->get('payment_id', '');
        $error = $request->query->get('error');
        $success = $error === null;

        try {
            $topUp = $this->topUpService->handleCallback($paymentId, $success);

            return $this->json([
                'topUpId' => $topUp->getId()->toRfc4122(),
                'status' => $topUp->getStatus(),
                'amount_cents' => $topUp->getAmountCents(),
                'method' => $topUp->getMethod(),
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * Get top-up history for current user.
     */
    #[Route('/history', methods: ['GET'])]
    public function history(Request $request): JsonResponse
    {
        $limit = min((int) $request->query->get('limit', 20), 100);
        $topUps = $this->topUpService->getHistory($this->getUser(), $limit);

        $items = array_map(fn($t) => [
            'id' => $t->getId()->toRfc4122(),
            'amount_cents' => $t->getAmountCents(),
            'method' => $t->getMethod(),
            'status' => $t->getStatus(),
            'source_bic' => $t->getSourceBic(),
            'reference' => $t->getReference(),
            'created_at' => $t->getCreatedAt()->format('c'),
            'completed_at' => $t->getCompletedAt()?->format('c'),
        ], $topUps);

        return $this->json(['topups' => $items]);
    }

    /**
     * List all EU/EEA PSD2 banks (optionally filtered by country).
     * PSD2 is mandatory — every bank listed here MUST support it.
     */
    #[Route('/banks', methods: ['GET'])]
    public function banks(Request $request): JsonResponse
    {
        $countryCode = $request->query->get('country');

        if ($countryCode !== null) {
            $banks = EuBankRegistry::getByCountry($countryCode);
        } else {
            $banks = EuBankRegistry::getAll();
        }

        return $this->json([
            'banks' => array_values($banks),
            'countries' => EuBankRegistry::getSupportedCountries(),
            'total' => count($banks),
        ]);
    }
}
