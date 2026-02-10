<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\DigitalEuro\DigitalEuroInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Digital Euro endpoints — readiness check + future operations.
 *
 * Pre-launch: returns regulatory parameters and availability status.
 * Post-launch (2029+): full P2P, POS, e-commerce, offline payments.
 */
#[Route('/api/digital-euro')]
class DigitalEuroController extends AbstractController
{
    public function __construct(
        private readonly DigitalEuroInterface $digitalEuro,
    ) {}

    /**
     * Check if the digital euro is available and return regulatory parameters.
     */
    #[Route('/status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        return $this->json([
            'available' => $this->digitalEuro->isAvailable(),
            'regulation' => $this->digitalEuro->getRegulationParameters(),
        ]);
    }

    /**
     * Open a Digital Euro Account (DEA).
     * Pre-launch: returns NOT_AVAILABLE with timeline info.
     */
    #[Route('/account', methods: ['POST'])]
    public function openAccount(): JsonResponse
    {
        if (!$this->digitalEuro->isAvailable()) {
            $params = $this->digitalEuro->getRegulationParameters();
            return $this->json([
                'error' => 'Digital euro is not yet available.',
                'pilotExpected' => $params['pilotExpected'],
                'issuanceExpected' => $params['issuanceExpected'],
                'info' => $params['ecbInfoUrl'],
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $user = $this->getUser();
        $result = $this->digitalEuro->openDigitalEuroAccount(
            $user->getId()->toRfc4122(),
            'User', // cardholder name from encrypted profile
        );

        return $this->json($result, Response::HTTP_CREATED);
    }

    /**
     * Get DEA balance.
     */
    #[Route('/balance/{deaId}', methods: ['GET'])]
    public function balance(string $deaId): JsonResponse
    {
        if (!$this->digitalEuro->isAvailable()) {
            return $this->json(['error' => 'Digital euro not yet available.'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return $this->json($this->digitalEuro->getBalance($deaId));
    }

    /**
     * P2P payment via digital euro.
     */
    #[Route('/pay/p2p', methods: ['POST'])]
    public function payP2P(): JsonResponse
    {
        if (!$this->digitalEuro->isAvailable()) {
            return $this->json(['error' => 'Digital euro not yet available.'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        // TODO: parse request body for fromDeaId, toAlias, amount
        return $this->json(['status' => 'NOT_IMPLEMENTED'], Response::HTTP_NOT_IMPLEMENTED);
    }

    /**
     * POS payment (NFC or QR) via digital euro.
     */
    #[Route('/pay/pos', methods: ['POST'])]
    public function payPos(): JsonResponse
    {
        if (!$this->digitalEuro->isAvailable()) {
            return $this->json(['error' => 'Digital euro not yet available.'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return $this->json(['status' => 'NOT_IMPLEMENTED'], Response::HTTP_NOT_IMPLEMENTED);
    }
}
