<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\UuidV6;

/**
 * User entity with zero-knowledge encryption and WebAuthn passkey authentication.
 *
 * Authentication is handled exclusively via WebAuthn passkeys (platform or
 * cross-platform/USB/NFC). No email or password is required.
 *
 * ENCRYPTED (only the user's Android device can read):
 *   email, firstName, lastName, phoneNumber, iban
 *
 * PLAINTEXT (operational data, not PII):
 *   displayName, externalPersonId, externalAccountId,
 *   kycStatus, roles, consent flags, timestamps
 *
 * The user's RSA-4096 public key is stored in publicKey.
 * The private key NEVER leaves Android Keystore.
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'app_user')]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidV6 $id;

    // ── Display name (required for passkey registration) ──

    #[ORM\Column(length: 130)]
    private string $displayName = '';

    // ── Encrypted PII (opaque blobs — backend cannot read) ──

    /** Envelope-encrypted email (RSA-OAEP + AES-256-GCM) */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $encryptedEmail = null;

    /** HMAC-SHA256 blind index of normalized email — optional legacy */
    #[ORM\Column(length: 64, unique: true, nullable: true)]
    private ?string $emailIndex = null;

    /** Envelope-encrypted first name */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $encryptedFirstName = null;

    /** Envelope-encrypted last name */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $encryptedLastName = null;

    /** Envelope-encrypted phone number */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $encryptedPhoneNumber = null;

    /** Envelope-encrypted IBAN */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $encryptedIban = null;

    // ── User's RSA public key (for envelope encryption) ──

    /** Base64-encoded DER (X.509 SubjectPublicKeyInfo) — RSA-4096 */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $publicKey = null;

    /** Key fingerprint (SHA-256 of public key DER) for key rotation tracking */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $publicKeyFingerprint = null;

    // ── Plaintext operational fields ──

    /** Legacy password field — nullable, no longer used for auth */
    #[ORM\Column(nullable: true)]
    private ?string $password = null;

    #[ORM\Column(type: Types::JSON)]
    private array $roles = [];

    /** PSD2 bank person ID — needed for webhook matching (not PII per se) */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $externalPersonId = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $externalAccountId = null;

    #[ORM\Column(length: 30)]
    private string $kycStatus = 'PENDING';

    // ── GDPR Consent Fields ──

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $gdprConsentGiven = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $gdprConsentAt = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $privacyPolicyVersion = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $deviceTrackingConsent = false;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $marketingConsent = false;

    #[ORM\Column(length: 30)]
    private string $legalBasis = 'contract';

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $anonymized = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $anonymizedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dataRetentionUntil = null;

    // ── Relations ──

    /** @var Collection<int, Card> */
    #[ORM\OneToMany(targetEntity: Card::class, mappedBy: 'user', cascade: ['persist'])]
    private Collection $cards;

    /** @var Collection<int, HceToken> */
    #[ORM\OneToMany(targetEntity: HceToken::class, mappedBy: 'user', cascade: ['persist'])]
    private Collection $hceTokens;

    /** @var Collection<int, WebAuthnCredential> */
    #[ORM\OneToMany(targetEntity: WebAuthnCredential::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    private Collection $webAuthnCredentials;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id = new UuidV6();
        $this->cards = new ArrayCollection();
        $this->hceTokens = new ArrayCollection();
        $this->webAuthnCredentials = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // ── Identity ──

    public function getId(): UuidV6 { return $this->id; }

    /**
     * Symfony UserInterface — returns UUID as user identifier.
     * Passkey auth identifies users by credential, not email.
     */
    public function getUserIdentifier(): string
    {
        return $this->id->toRfc4122();
    }

    // ── Display Name ──

    public function getDisplayName(): string { return $this->displayName; }
    public function setDisplayName(string $v): static { $this->displayName = $v; return $this; }

    // ── Encrypted PII accessors ──

    public function getEncryptedEmail(): ?string { return $this->encryptedEmail; }
    public function setEncryptedEmail(?string $v): static { $this->encryptedEmail = $v; return $this; }

    public function getEmailIndex(): ?string { return $this->emailIndex; }
    public function setEmailIndex(?string $v): static { $this->emailIndex = $v; return $this; }

    public function getEncryptedFirstName(): ?string { return $this->encryptedFirstName; }
    public function setEncryptedFirstName(?string $v): static { $this->encryptedFirstName = $v; return $this; }

    public function getEncryptedLastName(): ?string { return $this->encryptedLastName; }
    public function setEncryptedLastName(?string $v): static { $this->encryptedLastName = $v; return $this; }

    public function getEncryptedPhoneNumber(): ?string { return $this->encryptedPhoneNumber; }
    public function setEncryptedPhoneNumber(?string $v): static { $this->encryptedPhoneNumber = $v; return $this; }

    public function getEncryptedIban(): ?string { return $this->encryptedIban; }
    public function setEncryptedIban(?string $v): static { $this->encryptedIban = $v; return $this; }

    // ── Public key ──

    public function getPublicKey(): ?string { return $this->publicKey; }
    public function setPublicKey(?string $v): static { $this->publicKey = $v; return $this; }

    public function getPublicKeyFingerprint(): ?string { return $this->publicKeyFingerprint; }
    public function setPublicKeyFingerprint(?string $v): static { $this->publicKeyFingerprint = $v; return $this; }

    public function hasPublicKey(): bool { return $this->publicKey !== null && $this->publicKey !== ''; }

    // ── Auth ──

    public function getPassword(): ?string { return $this->password; }
    public function setPassword(?string $password): static { $this->password = $password; return $this; }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }
    public function setRoles(array $roles): static { $this->roles = $roles; return $this; }

    public function eraseCredentials(): void {}

    // ── Operational plaintext ──

    public function getExternalPersonId(): ?string { return $this->externalPersonId; }
    public function setExternalPersonId(?string $v): static { $this->externalPersonId = $v; return $this; }

    public function getExternalAccountId(): ?string { return $this->externalAccountId; }
    public function setExternalAccountId(?string $v): static { $this->externalAccountId = $v; return $this; }

    public function getKycStatus(): string { return $this->kycStatus; }
    public function setKycStatus(string $v): static { $this->kycStatus = $v; return $this; }

    // ── GDPR ──

    public function isGdprConsentGiven(): bool { return $this->gdprConsentGiven; }
    public function setGdprConsentGiven(bool $v): static { $this->gdprConsentGiven = $v; return $this; }

    public function getGdprConsentAt(): ?\DateTimeImmutable { return $this->gdprConsentAt; }
    public function setGdprConsentAt(?\DateTimeImmutable $v): static { $this->gdprConsentAt = $v; return $this; }

    public function getPrivacyPolicyVersion(): ?string { return $this->privacyPolicyVersion; }
    public function setPrivacyPolicyVersion(?string $v): static { $this->privacyPolicyVersion = $v; return $this; }

    public function isDeviceTrackingConsent(): bool { return $this->deviceTrackingConsent; }
    public function setDeviceTrackingConsent(bool $v): static { $this->deviceTrackingConsent = $v; return $this; }

    public function isMarketingConsent(): bool { return $this->marketingConsent; }
    public function setMarketingConsent(bool $v): static { $this->marketingConsent = $v; return $this; }

    public function getLegalBasis(): string { return $this->legalBasis; }
    public function setLegalBasis(string $v): static { $this->legalBasis = $v; return $this; }

    public function isAnonymized(): bool { return $this->anonymized; }
    public function setAnonymized(bool $v): static { $this->anonymized = $v; return $this; }

    public function getAnonymizedAt(): ?\DateTimeImmutable { return $this->anonymizedAt; }
    public function setAnonymizedAt(?\DateTimeImmutable $v): static { $this->anonymizedAt = $v; return $this; }

    public function getDataRetentionUntil(): ?\DateTimeImmutable { return $this->dataRetentionUntil; }
    public function setDataRetentionUntil(?\DateTimeImmutable $v): static { $this->dataRetentionUntil = $v; return $this; }

    /**
     * GDPR Art. 17 — Erase personal data.
     * Encrypted blobs are overwritten. Blind indexes are cleared.
     * Transaction records retained (AML).
     */
    public function anonymize(): void
    {
        $this->encryptedEmail = null;
        $this->emailIndex = 'anon_' . bin2hex(random_bytes(16));
        $this->encryptedFirstName = null;
        $this->encryptedLastName = null;
        $this->encryptedPhoneNumber = null;
        $this->encryptedIban = null;
        $this->publicKey = null;
        $this->publicKeyFingerprint = null;
        $this->password = null;
        $this->displayName = 'Deleted User';
        $this->roles = [];
        $this->anonymized = true;
        $this->anonymizedAt = new \DateTimeImmutable();
        $this->marketingConsent = false;
        $this->deviceTrackingConsent = false;
    }

    // ── Relations ──

    /** @return Collection<int, Card> */
    public function getCards(): Collection { return $this->cards; }

    /** @return Collection<int, HceToken> */
    public function getHceTokens(): Collection { return $this->hceTokens; }

    /** @return Collection<int, WebAuthnCredential> */
    public function getWebAuthnCredentials(): Collection { return $this->webAuthnCredentials; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
}
