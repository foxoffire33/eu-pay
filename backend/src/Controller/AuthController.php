<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Crypto\BlindIndexService;
use App\Service\Crypto\EnvelopeEncryptionService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Auth: register + login. Zero-knowledge architecture.
 *
 * Registration flow (PSD2 Open Banking):
 * 1. Android generates RSA-4096 keypair in Keystore
 * 2. Sends encrypted PII + public key
 * 3. Backend stores ONLY: encrypted blobs, public key, blind indexes
 * 4. PSD2 bank account linkage happens separately via /api/account/create
 * 5. No PII stored in plaintext on our backend
 */
#[Route('/api')]
class AuthController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly EnvelopeEncryptionService $envelope,
        private readonly BlindIndexService $blindIndex,
        private readonly LoggerInterface $logger,
    ) {}

    #[Route('/register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = $request->toArray();

        // ── Validate required fields ──
        $required = ['password', 'public_key', 'encrypted_email', 'encrypted_first_name',
                      'encrypted_last_name', 'encrypted_phone', 'email_plain',
                      'gdpr_consent', 'privacy_policy_version'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return $this->json(['error' => "Missing field: {$field}"], Response::HTTP_BAD_REQUEST);
            }
        }

        if (!$data['gdpr_consent']) {
            return $this->json(['error' => 'GDPR consent is required under Art. 6(1)(a)'], Response::HTTP_BAD_REQUEST);
        }

        // ── Check duplicate by email blind index ──
        $emailIndex = $this->blindIndex->compute($data['email_plain']);
        if ($this->em->getRepository(User::class)->findByEmailIndex($emailIndex)) {
            return $this->json(['error' => 'An account with this email already exists'], Response::HTTP_CONFLICT);
        }

        // ── Create user — store only encrypted blobs + blind indexes ──
        $user = new User();
        $user->setEncryptedEmail($data['encrypted_email']);
        $user->setEncryptedFirstName($data['encrypted_first_name']);
        $user->setEncryptedLastName($data['encrypted_last_name']);
        $user->setEncryptedPhone($data['encrypted_phone']);
        $user->setPublicKey($data['public_key']);
        $user->setEmailBlindIndex($emailIndex);

        if (isset($data['phone_plain'])) {
            $user->setPhoneBlindIndex($this->blindIndex->compute($data['phone_plain']));
        }

        $user->setPassword($this->hasher->hashPassword($user, $data['password']));
        $user->setGdprConsentAt(new \DateTimeImmutable());
        $user->setPrivacyPolicyVersion($data['privacy_policy_version']);

        $this->em->persist($user);
        $this->em->flush();

        $this->logger->info('User registered (PSD2 Open Banking)', [
            'userId' => $user->getId()->toRfc4122(),
        ]);

        return $this->json([
            'user_id' => $user->getId()->toRfc4122(),
            'message' => 'Registration successful. Link your bank account via /api/account/create.',
        ], Response::HTTP_CREATED);
    }

    #[Route('/me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->getUser();

        return $this->json([
            'id' => $user->getId()->toRfc4122(),
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
            $user->setEncryptedPhone($data['re_encrypted_phone']);
        }

        $this->em->flush();

        return $this->json(['message' => 'Key rotated. Re-encrypted fields updated.']);
    }
}
