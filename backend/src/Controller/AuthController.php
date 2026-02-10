<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Authenticated user profile endpoints.
 * Registration and login are now handled by WebAuthnController via passkeys.
 */
#[Route('/api')]
class AuthController extends AbstractController
{
    #[Route('/me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json([
            'id' => $user->getId()->toRfc4122(),
            'display_name' => $user->getDisplayName(),
            'encrypted_email' => $user->getEncryptedEmail(),
            'encrypted_first_name' => $user->getEncryptedFirstName(),
            'encrypted_last_name' => $user->getEncryptedLastName(),
            'public_key' => $user->getPublicKey(),
            'has_bank_account' => $user->getExternalAccountId() !== null,
            'created_at' => $user->getCreatedAt()->format('c'),
            'gdpr_consent_at' => $user->getGdprConsentAt()?->format('c'),
        ]);
    }

    #[Route('/me/rotate-key', methods: ['POST'])]
    public function rotateKey(Request $request): JsonResponse
    {
        $data = $request->toArray();
        $newKey = $data['public_key'] ?? '';
        if (empty($newKey)) {
            return $this->json(['error' => 'New public key is required'], Response::HTTP_BAD_REQUEST);
        }

        /** @var User $user */
        $user = $this->getUser();
        $user->setPublicKey($newKey);

        if (isset($data['re_encrypted_email'])) {
            $user->setEncryptedEmail($data['re_encrypted_email']);
        }
        if (isset($data['re_encrypted_first_name'])) {
            $user->setEncryptedFirstName($data['re_encrypted_first_name']);
        }
        if (isset($data['re_encrypted_last_name'])) {
            $user->setEncryptedLastName($data['re_encrypted_last_name']);
        }
        if (isset($data['re_encrypted_phone'])) {
            $user->setEncryptedPhoneNumber($data['re_encrypted_phone']);
        }

        return $this->json(['message' => 'Key rotated. Re-encrypted fields updated.']);
    }
}
