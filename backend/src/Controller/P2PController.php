<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\P2PTransferService;
use App\Service\EuBankRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Peer-to-peer money transfers.
 *
 * Internal: EU Pay user → EU Pay user (instant, free).
 * External: EU Pay user → any EU/EEA IBAN via SEPA (all PSD2 banks).
 *
 * Phone payment flow (PSD2 PISP):
 *  1. User opens EU Pay app on phone
 *  2. Selects "Send Money" → enters recipient (email or IBAN)
 *  3. Authenticates via biometric (PSD2 SCA)
 *  4. Transfer executes instantly (internal) or via SEPA (external)
 */
#[Route('/api/p2p')]
#[IsGranted('ROLE_USER')]
class P2PController extends AbstractController
{
    public function __construct(
        private readonly P2PTransferService $p2pService,
    ) {
    }

    /**
     * Send money to another EU Pay user (by email).
     * Instant, zero fees. Recipient decrypts message with their private key.
     */
    #[Route('/send/user', methods: ['POST'])]
    public function sendToUser(Request $request): JsonResponse
    {
        $data = $request->toArray();

        try {
            $result = $this->p2pService->sendToUser(
                sender: $this->getUser(),
                recipientEmail: $data['recipient_email'] ?? '',
                amountCents: (int) ($data['amount_cents'] ?? 0),
                message: $data['message'] ?? null,
            );

            return $this->json($result, Response::HTTP_CREATED);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Send money to any EU/EEA bank account via SEPA.
     *
     * Supports ALL PSD2-compliant banks: Rabobank, ING, ABN AMRO,
     * Deutsche Bank, BNP Paribas, UniCredit, Santander, CaixaBank,
     * PKO, Nordea, Danske Bank, Erste, Intesa Sanpaolo, and 140+ more.
     */
    #[Route('/send/iban', methods: ['POST'])]
    public function sendToIban(Request $request): JsonResponse
    {
        $data = $request->toArray();

        try {
            $result = $this->p2pService->sendToIban(
                sender: $this->getUser(),
                recipientIban: $data['recipient_iban'] ?? '',
                recipientName: $data['recipient_name'] ?? '',
                amountCents: (int) ($data['amount_cents'] ?? 0),
                recipientBic: $data['recipient_bic'] ?? null,
                message: $data['message'] ?? null,
            );

            return $this->json($result, Response::HTTP_CREATED);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Get transfer history (sent + received).
     */
    #[Route('/history', methods: ['GET'])]
    public function history(Request $request): JsonResponse
    {
        $limit = min((int) $request->query->get('limit', 20), 100);
        $transfers = $this->p2pService->getHistory($this->getUser(), $limit);

        $userId = $this->getUser()->getId()->toRfc4122();

        $items = array_map(fn($t) => [
            'id' => $t->getId()->toRfc4122(),
            'type' => $t->getType(),
            'direction' => $t->getSender()->getId()->toRfc4122() === $userId ? 'sent' : 'received',
            'amount_cents' => $t->getAmountCents(),
            'status' => $t->getStatus(),
            'recipient_bic' => $t->getRecipientBic(),
            'encrypted_recipient_name' => $t->getEncryptedRecipientName(),
            'encrypted_message' => $t->getEncryptedMessage(),
            'reference' => $t->getReference(),
            'created_at' => $t->getCreatedAt()->format('c'),
            'completed_at' => $t->getCompletedAt()?->format('c'),
        ], $transfers);

        return $this->json(['transfers' => $items]);
    }

    /**
     * List EU/EEA PSD2 banks for IBAN transfers.
     */
    #[Route('/banks', methods: ['GET'])]
    public function banks(Request $request): JsonResponse
    {
        $countryCode = $request->query->get('country');

        $banks = $countryCode !== null
            ? EuBankRegistry::getByCountry($countryCode)
            : EuBankRegistry::getAll();

        return $this->json([
            'banks' => array_values($banks),
            'countries' => EuBankRegistry::getSupportedCountries(),
            'total' => count($banks),
        ]);
    }
}
