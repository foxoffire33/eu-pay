<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\CardRepository;
use App\Repository\HceTokenRepository;
use App\Service\HceProvisioningService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/hce')]
class HceController extends AbstractController
{
    public function __construct(
        private readonly HceProvisioningService $hceService,
        private readonly CardRepository $cardRepo,
        private readonly HceTokenRepository $hceTokenRepo,
    ) {}

    /**
     * Provision a card for direct HCE NFC payments on this device.
     */
    #[Route('/provision', methods: ['POST'])]
    public function provision(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['card_id']) || empty($data['device_fingerprint'])) {
            return $this->json(
                ['error' => 'card_id and device_fingerprint required'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $card = $this->cardRepo->find($data['card_id']);
        if (!$card || (string) $card->getUser()->getId() !== (string) $user->getId()) {
            return $this->json(['error' => 'Card not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $token = $this->hceService->provisionCard($user, $card, $data['device_fingerprint']);
        } catch (\RuntimeException) {
            return $this->json(['error' => 'Unable to provision card for NFC payment. Ensure the card is active.'], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'token_id'   => (string) $token->getId(),
            'status'     => $token->getStatus(),
            'expires_at' => $token->getExpiresAt()->format('c'),
        ], Response::HTTP_CREATED);
    }

    /**
     * Fetch the EMV payment payload for an NFC tap.
     * Called by the Android app right before / during HCE service activation.
     */
    #[Route('/payload/{tokenId}', methods: ['GET'])]
    public function payload(string $tokenId): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $token = $this->hceTokenRepo->find($tokenId);
        if (!$token || (string) $token->getUser()->getId() !== (string) $user->getId()) {
            return $this->json(['error' => 'Token not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $payload = $this->hceService->getPaymentPayload($token);
        } catch (\RuntimeException) {
            return $this->json(['error' => 'Payment token unavailable. It may be expired or inactive — try refreshing.'], Response::HTTP_CONFLICT);
        }

        return $this->json($payload);
    }

    /**
     * Refresh session keys for continued NFC usage.
     */
    #[Route('/refresh/{tokenId}', methods: ['POST'])]
    public function refresh(string $tokenId): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $token = $this->hceTokenRepo->find($tokenId);
        if (!$token || (string) $token->getUser()->getId() !== (string) $user->getId()) {
            return $this->json(['error' => 'Token not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $result = $this->hceService->refreshSessionKey($token);
        } catch (\RuntimeException) {
            return $this->json(['error' => 'Unable to refresh token. It may be deactivated — re-provision required.'], Response::HTTP_CONFLICT);
        }

        return $this->json($result);
    }

    /**
     * Deactivate an HCE token (e.g., user switches device).
     */
    #[Route('/deactivate/{tokenId}', methods: ['POST'])]
    public function deactivate(string $tokenId): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $token = $this->hceTokenRepo->find($tokenId);
        if (!$token || (string) $token->getUser()->getId() !== (string) $user->getId()) {
            return $this->json(['error' => 'Token not found'], Response::HTTP_NOT_FOUND);
        }

        $this->hceService->deactivateToken($token);

        return $this->json(['status' => 'DEACTIVATED']);
    }

    /**
     * List all active HCE tokens for the current user.
     */
    #[Route('/tokens', methods: ['GET'])]
    public function listTokens(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $tokens = $this->hceTokenRepo->findActiveByUser($user);

        return $this->json(array_map(fn($t) => [
            'token_id'           => (string) $t->getId(),
            'card_id'            => (string) $t->getCard()->getId(),
            'card_scheme'        => $t->getCardScheme(),
            'device_fingerprint' => $t->getDeviceFingerprint(),
            'status'             => $t->getStatus(),
            'atc'                => $t->getAtc(),
            'expires_at'         => $t->getExpiresAt()->format('c'),
        ], $tokens));
    }
}
