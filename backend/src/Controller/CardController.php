<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\CardRepository;
use App\Service\CardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/cards')]
class CardController extends AbstractController
{
    public function __construct(
        private readonly CardService $cardService,
        private readonly CardRepository $cardRepo,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $cards = $this->cardService->getUserCards($user);

        return $this->json(array_map(fn($c) => [
            'id'          => (string) $c->getId(),
            'type'        => $c->getType(),
            'scheme'      => $c->getScheme(),
            'status'      => $c->getStatus(),
            'last_four'   => $c->getLastFourDigits(),
            'expiry_date' => $c->getExpiryDate(),
            'created_at'  => $c->getCreatedAt()->format('c'),
        ], $cards));
    }

    #[Route('/virtual', methods: ['POST'])]
    public function createVirtual(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true) ?? [];

        // Client sends cardholder name (decrypted locally) as ephemeral param for PSD2 bank
        $cardholderName = $data['cardholder_name'] ?? 'CARDHOLDER';

        try {
            $card = $this->cardService->createVirtualCard($user, $cardholderName);
        } catch (\RuntimeException) {
            return $this->json(['error' => 'Unable to create card. Please ensure your account is active.'], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'id'     => (string) $card->getId(),
            'type'   => $card->getType(),
            'scheme' => $card->getScheme(),
            'status' => $card->getStatus(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}/activate', methods: ['POST'])]
    public function activate(string $id, Request $request): JsonResponse
    {
        $card = $this->cardRepo->find($id);
        if (!$card || (string) $card->getUser()->getId() !== (string) $this->getUser()->getId()) {
            return $this->json(['error' => 'Card not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $card = $this->cardService->activateCard($card, $data['verification_token'] ?? '');

        return $this->json(['status' => $card->getStatus()]);
    }

    #[Route('/{id}/block', methods: ['POST'])]
    public function block(string $id): JsonResponse
    {
        $card = $this->cardRepo->find($id);
        if (!$card || (string) $card->getUser()->getId() !== (string) $this->getUser()->getId()) {
            return $this->json(['error' => 'Card not found'], Response::HTTP_NOT_FOUND);
        }

        $card = $this->cardService->blockCard($card);
        return $this->json(['status' => $card->getStatus()]);
    }

    #[Route('/{id}/unblock', methods: ['POST'])]
    public function unblock(string $id): JsonResponse
    {
        $card = $this->cardRepo->find($id);
        if (!$card || (string) $card->getUser()->getId() !== (string) $this->getUser()->getId()) {
            return $this->json(['error' => 'Card not found'], Response::HTTP_NOT_FOUND);
        }

        $card = $this->cardService->unblockCard($card);
        return $this->json(['status' => $card->getStatus()]);
    }
}
