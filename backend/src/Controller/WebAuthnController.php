<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\WebAuthn\WebAuthnService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/passkey')]
class WebAuthnController extends AbstractController
{
    public function __construct(
        private readonly WebAuthnService $webAuthnService,
        private readonly EntityManagerInterface $em,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Step 1 of registration: create user and return WebAuthn creation options.
     *
     * display_name is optional — if omitted, derived from SHA-512 of public_key.
     */
    #[Route('/register/options', methods: ['POST'])]
    public function registerOptions(Request $request): JsonResponse
    {
        $data = $request->toArray();

        if (empty($data['gdpr_consent'])) {
            return $this->json(
                ['error' => 'GDPR consent is required under Art. 6(1)(a)'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Derive displayName: explicit > SHA-512(public_key) > UUID
        $displayName = trim($data['display_name'] ?? '');
        if ($displayName === '' && !empty($data['public_key'])) {
            $displayName = hash('sha512', $data['public_key']);
        }
        if ($displayName === '') {
            $displayName = bin2hex(random_bytes(16));
        }

        // Create user
        $user = new User();
        $user->setDisplayName($displayName);
        $user->setGdprConsentGiven(true);
        $user->setGdprConsentAt(new \DateTimeImmutable());
        $user->setPrivacyPolicyVersion($data['privacy_policy_version'] ?? '1.0');

        // Store optional encrypted PII
        if (!empty($data['encrypted_email'])) {
            $user->setEncryptedEmail($data['encrypted_email']);
        }
        if (!empty($data['encrypted_first_name'])) {
            $user->setEncryptedFirstName($data['encrypted_first_name']);
        }
        if (!empty($data['encrypted_last_name'])) {
            $user->setEncryptedLastName($data['encrypted_last_name']);
        }
        if (!empty($data['public_key'])) {
            $user->setPublicKey($data['public_key']);
        }

        $this->em->persist($user);
        $this->em->flush();

        try {
            $result = $this->webAuthnService->generateRegistrationOptions($user);
        } catch (\Throwable $e) {
            $this->logger->error('WebAuthn registration options failed', ['error' => $e->getMessage()]);
            return $this->json(['error' => 'Failed to generate registration options'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json($result);
    }

    /**
     * Step 2 of registration: verify attestation and issue JWT.
     */
    #[Route('/register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = $request->toArray();

        $challengeToken = $data['challenge_token'] ?? '';
        $credential = $data['credential'] ?? null;

        if (empty($challengeToken) || $credential === null) {
            return $this->json(
                ['error' => 'challenge_token and credential are required'],
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $credentialJson = is_string($credential) ? $credential : json_encode($credential);
            $webAuthnCred = $this->webAuthnService->verifyRegistration($challengeToken, $credentialJson);
            $user = $webAuthnCred->getUser();
            $token = $this->jwtManager->create($user);

            $this->logger->info('User registered via passkey', [
                'userId' => $user->getId()->toRfc4122(),
            ]);

            return $this->json([
                'token' => $token,
                'user_id' => $user->getId()->toRfc4122(),
            ], Response::HTTP_CREATED);
        } catch (\Throwable $e) {
            $this->logger->error('WebAuthn registration verification failed', ['error' => $e->getMessage()]);
            return $this->json(
                ['error' => 'Registration verification failed: ' . $e->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        }
    }

    /**
     * Step 1 of login: return WebAuthn request options.
     */
    #[Route('/login/options', methods: ['POST'])]
    public function loginOptions(): JsonResponse
    {
        try {
            $result = $this->webAuthnService->generateLoginOptions();
        } catch (\Throwable $e) {
            $this->logger->error('WebAuthn login options failed', ['error' => $e->getMessage()]);
            return $this->json(['error' => 'Failed to generate login options'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json($result);
    }

    /**
     * Step 2 of login: verify assertion and issue JWT.
     */
    #[Route('/login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = $request->toArray();

        $challengeToken = $data['challenge_token'] ?? '';
        $credential = $data['credential'] ?? null;

        if (empty($challengeToken) || $credential === null) {
            return $this->json(
                ['error' => 'challenge_token and credential are required'],
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $credentialJson = is_string($credential) ? $credential : json_encode($credential);
            $user = $this->webAuthnService->verifyLogin($challengeToken, $credentialJson);
            $token = $this->jwtManager->create($user);

            $this->logger->info('User logged in via passkey', [
                'userId' => $user->getId()->toRfc4122(),
            ]);

            return $this->json([
                'token' => $token,
                'user_id' => $user->getId()->toRfc4122(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('WebAuthn login verification failed', ['error' => $e->getMessage()]);
            return $this->json(
                ['error' => 'Authentication failed'],
                Response::HTTP_UNAUTHORIZED
            );
        }
    }
}
