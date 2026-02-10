<?php

declare(strict_types=1);

namespace App\Service\WebAuthn;

use App\Entity\User;
use App\Entity\WebAuthnCredential;
use App\Repository\WebAuthnCredentialRepository;
use Cose\Algorithms;
use Doctrine\ORM\EntityManagerInterface;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\Uuid;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\CredentialRecord;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\TrustPath\EmptyTrustPath;

class WebAuthnService
{
    private readonly SerializerInterface $webauthnSerializer;
    private readonly AuthenticatorAttestationResponseValidator $attestationValidator;
    private readonly AuthenticatorAssertionResponseValidator $assertionValidator;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly WebAuthnCredentialRepository $credentialRepo,
        private readonly CacheItemPoolInterface $webauthnChallengeCache,
        private readonly string $rpId,
        private readonly string $rpName,
    ) {
        $attestationManager = AttestationStatementSupportManager::create();
        $this->webauthnSerializer = (new WebauthnSerializerFactory($attestationManager))->create();

        $factory = new CeremonyStepManagerFactory();
        $factory->setAllowedOrigins(['https://' . $this->rpId, 'android:apk-key-hash:*']);

        $this->attestationValidator = AuthenticatorAttestationResponseValidator::create(
            $factory->creationCeremony()
        );
        $this->assertionValidator = AuthenticatorAssertionResponseValidator::create(
            $factory->requestCeremony()
        );
    }

    /**
     * Generate registration options for a new passkey.
     *
     * @return array{challenge_token: string, options: array}
     */
    public function generateRegistrationOptions(User $user): array
    {
        $challenge = random_bytes(32);

        $rp = PublicKeyCredentialRpEntity::create($this->rpName, $this->rpId);
        $userEntity = PublicKeyCredentialUserEntity::create(
            $user->getDisplayName(),
            $user->getId()->toRfc4122(),
            $user->getDisplayName(),
        );

        $options = PublicKeyCredentialCreationOptions::create(
            $rp,
            $userEntity,
            Base64UrlSafe::encodeUnpadded($challenge),
            [
                PublicKeyCredentialParameters::createPk(Algorithms::COSE_ALGORITHM_ES256),
                PublicKeyCredentialParameters::createPk(Algorithms::COSE_ALGORITHM_RS256),
            ],
            AuthenticatorSelectionCriteria::create(
                null, // allow both platform and cross-platform (USB/NFC)
                AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_PREFERRED,
                AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_PREFERRED,
            ),
            PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
            [],
            60000, // 60s timeout
        );

        // Store challenge + options in cache
        $challengeToken = bin2hex(random_bytes(32));
        $cacheItem = $this->webauthnChallengeCache->getItem('reg_' . $challengeToken);
        $cacheItem->set([
            'options' => $this->webauthnSerializer->serialize($options, 'json'),
            'user_id' => $user->getId()->toRfc4122(),
        ]);
        $cacheItem->expiresAfter(120);
        $this->webauthnChallengeCache->save($cacheItem);

        $optionsJson = json_decode($this->webauthnSerializer->serialize($options, 'json'), true);

        return [
            'challenge_token' => $challengeToken,
            'options' => $optionsJson,
        ];
    }

    /**
     * Verify registration response and store credential.
     */
    public function verifyRegistration(string $challengeToken, string $credentialJson): WebAuthnCredential
    {
        // Retrieve stored challenge
        $cacheItem = $this->webauthnChallengeCache->getItem('reg_' . $challengeToken);
        if (!$cacheItem->isHit()) {
            throw new \RuntimeException('Challenge expired or invalid');
        }

        $cached = $cacheItem->get();
        $this->webauthnChallengeCache->deleteItem('reg_' . $challengeToken);

        $storedOptions = $this->webauthnSerializer->deserialize(
            $cached['options'],
            PublicKeyCredentialCreationOptions::class,
            'json'
        );

        // Deserialize client response
        /** @var PublicKeyCredential $publicKeyCredential */
        $publicKeyCredential = $this->webauthnSerializer->deserialize(
            $credentialJson,
            PublicKeyCredential::class,
            'json'
        );

        if (!$publicKeyCredential->response instanceof AuthenticatorAttestationResponse) {
            throw new \RuntimeException('Invalid attestation response');
        }

        // Verify attestation
        $credentialRecord = $this->attestationValidator->check(
            $publicKeyCredential->response,
            $storedOptions,
            $this->rpId,
        );

        // Find user
        $user = $this->em->getRepository(User::class)->find($cached['user_id']);
        if (!$user) {
            throw new \RuntimeException('User not found');
        }

        // Store credential
        $webAuthnCred = new WebAuthnCredential();
        $webAuthnCred->setUser($user);
        $webAuthnCred->setCredentialId(Base64UrlSafe::encodeUnpadded($credentialRecord->publicKeyCredentialId));
        $webAuthnCred->setCredentialPublicKey(Base64UrlSafe::encodeUnpadded($credentialRecord->credentialPublicKey));
        $webAuthnCred->setSignCount($credentialRecord->counter);
        $webAuthnCred->setAaguid($credentialRecord->aaguid->toRfc4122());
        $webAuthnCred->setTransports($credentialRecord->transports);
        $webAuthnCred->setAttestationType($credentialRecord->attestationType);
        $webAuthnCred->setDeviceName('Passkey');

        $this->em->persist($webAuthnCred);
        $this->em->flush();

        return $webAuthnCred;
    }

    /**
     * Generate login options (discoverable credential flow).
     *
     * @return array{challenge_token: string, options: array}
     */
    public function generateLoginOptions(): array
    {
        $challenge = random_bytes(32);

        $options = PublicKeyCredentialRequestOptions::create(
            Base64UrlSafe::encodeUnpadded($challenge),
            $this->rpId,
            [], // empty allowCredentials for discoverable credentials
            PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_PREFERRED,
            60000, // 60s timeout
        );

        $challengeToken = bin2hex(random_bytes(32));
        $cacheItem = $this->webauthnChallengeCache->getItem('auth_' . $challengeToken);
        $cacheItem->set([
            'options' => $this->webauthnSerializer->serialize($options, 'json'),
        ]);
        $cacheItem->expiresAfter(120);
        $this->webauthnChallengeCache->save($cacheItem);

        $optionsJson = json_decode($this->webauthnSerializer->serialize($options, 'json'), true);

        return [
            'challenge_token' => $challengeToken,
            'options' => $optionsJson,
        ];
    }

    /**
     * Verify login response and return the authenticated user.
     */
    public function verifyLogin(string $challengeToken, string $credentialJson): User
    {
        // Retrieve stored challenge
        $cacheItem = $this->webauthnChallengeCache->getItem('auth_' . $challengeToken);
        if (!$cacheItem->isHit()) {
            throw new \RuntimeException('Challenge expired or invalid');
        }

        $cached = $cacheItem->get();
        $this->webauthnChallengeCache->deleteItem('auth_' . $challengeToken);

        $storedOptions = $this->webauthnSerializer->deserialize(
            $cached['options'],
            PublicKeyCredentialRequestOptions::class,
            'json'
        );

        // Deserialize client response
        /** @var PublicKeyCredential $publicKeyCredential */
        $publicKeyCredential = $this->webauthnSerializer->deserialize(
            $credentialJson,
            PublicKeyCredential::class,
            'json'
        );

        // Find stored credential by credential ID
        $credentialIdEncoded = Base64UrlSafe::encodeUnpadded($publicKeyCredential->rawId);
        $storedCred = $this->credentialRepo->findByCredentialId($credentialIdEncoded);
        if (!$storedCred) {
            throw new \RuntimeException('Unknown credential');
        }

        // Build CredentialRecord from stored data
        $credentialRecord = CredentialRecord::create(
            Base64UrlSafe::decodeNoPadding($storedCred->getCredentialId()),
            'public-key',
            $storedCred->getTransports(),
            $storedCred->getAttestationType(),
            new EmptyTrustPath(),
            Uuid::fromRfc4122($storedCred->getAaguid()),
            Base64UrlSafe::decodeNoPadding($storedCred->getCredentialPublicKey()),
            $storedCred->getUser()->getId()->toRfc4122(),
            $storedCred->getSignCount(),
        );

        // Verify assertion
        $updatedRecord = $this->assertionValidator->check(
            $credentialRecord,
            $publicKeyCredential->response,
            $storedOptions,
            $this->rpId,
            $storedCred->getUser()->getId()->toRfc4122(),
        );

        // Update sign count
        $storedCred->setSignCount($updatedRecord->counter);
        $storedCred->markUsed();
        $this->em->flush();

        return $storedCred->getUser();
    }
}
