<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\LinkedBankAccountRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\UuidV6;

/**
 * PSD2 AISP-linked bank account.
 *
 * Represents a user's external bank account connected via PSD2 consent.
 * IBAN is envelope-encrypted (zero-knowledge). Balance and transactions
 * are read in real-time via the PSD2 AISP API using the stored consentId.
 *
 * Consent validity: max 90 days per PSD2 RTS.
 */
#[ORM\Entity(repositoryClass: LinkedBankAccountRepository::class)]
#[ORM\Table(name: 'linked_bank_accounts')]
#[ORM\Index(fields: ['user', 'status'], name: 'idx_linked_bank_user_status')]
#[ORM\Index(fields: ['consentId'], name: 'idx_linked_bank_consent')]
#[ORM\HasLifecycleCallbacks]
class LinkedBankAccount
{
    public const STATUS_PENDING_CONSENT = 'pending_consent';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_CONSENT_EXPIRED = 'consent_expired';
    public const STATUS_REVOKED = 'revoked';
    public const STATUS_FAILED = 'failed';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidV6 $id;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'linkedBankAccounts')]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    /** Envelope-encrypted IBAN (RSA-OAEP + AES-256-GCM) */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $encryptedIban = null;

    /** First 2 characters of IBAN (country code, not PII) */
    #[ORM\Column(length: 2)]
    private string $ibanCountryCode;

    /** Last 4 digits of IBAN (for display masking) */
    #[ORM\Column(length: 4)]
    private string $ibanLastFour;

    /** BIC of the connected bank */
    #[ORM\Column(length: 11, nullable: true)]
    private ?string $bankBic = null;

    /** Human-readable bank name from EuBankRegistry */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $bankName = null;

    /** PSD2 AISP consent ID from ASPSP */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $consentId = null;

    /** ASPSP account resource ID */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $externalAccountId = null;

    /** SCA redirect URL (temporary, used during linking) */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $authorisationUrl = null;

    /** Consent expiry (max 90 days per PSD2 RTS) */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $consentValidUntil = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING_CONSENT;

    /** User-assigned label (e.g., "My Savings") */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $label = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id = new UuidV6();
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

    public function getUser(): User { return $this->user; }
    public function setUser(User $user): static { $this->user = $user; return $this; }

    // ── Encrypted IBAN ──

    public function getEncryptedIban(): ?string { return $this->encryptedIban; }
    public function setEncryptedIban(?string $v): static { $this->encryptedIban = $v; return $this; }

    public function getIbanCountryCode(): string { return $this->ibanCountryCode; }
    public function setIbanCountryCode(string $v): static { $this->ibanCountryCode = $v; return $this; }

    public function getIbanLastFour(): string { return $this->ibanLastFour; }
    public function setIbanLastFour(string $v): static { $this->ibanLastFour = $v; return $this; }

    // ── Bank info ──

    public function getBankBic(): ?string { return $this->bankBic; }
    public function setBankBic(?string $v): static { $this->bankBic = $v; return $this; }

    public function getBankName(): ?string { return $this->bankName; }
    public function setBankName(?string $v): static { $this->bankName = $v; return $this; }

    // ── PSD2 consent ──

    public function getConsentId(): ?string { return $this->consentId; }
    public function setConsentId(?string $v): static { $this->consentId = $v; return $this; }

    public function getExternalAccountId(): ?string { return $this->externalAccountId; }
    public function setExternalAccountId(?string $v): static { $this->externalAccountId = $v; return $this; }

    public function getAuthorisationUrl(): ?string { return $this->authorisationUrl; }
    public function setAuthorisationUrl(?string $v): static { $this->authorisationUrl = $v; return $this; }

    public function getConsentValidUntil(): ?\DateTimeImmutable { return $this->consentValidUntil; }
    public function setConsentValidUntil(?\DateTimeImmutable $v): static { $this->consentValidUntil = $v; return $this; }

    // ── Status ──

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): static { $this->status = $v; return $this; }

    public function getLabel(): ?string { return $this->label; }
    public function setLabel(?string $v): static { $this->label = $v; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    // ── State transitions ──

    public function activate(string $consentId, string $externalAccountId): static
    {
        $this->consentId = $consentId;
        $this->externalAccountId = $externalAccountId;
        $this->status = self::STATUS_ACTIVE;
        return $this;
    }

    public function markConsentExpired(): static
    {
        $this->status = self::STATUS_CONSENT_EXPIRED;
        return $this;
    }

    public function revoke(): static
    {
        $this->status = self::STATUS_REVOKED;
        $this->encryptedIban = null;
        return $this;
    }

    public function markFailed(): static
    {
        $this->status = self::STATUS_FAILED;
        return $this;
    }

    public function refreshConsent(string $newConsentId, \DateTimeImmutable $newValidUntil): static
    {
        $this->consentId = $newConsentId;
        $this->consentValidUntil = $newValidUntil;
        $this->status = self::STATUS_ACTIVE;
        return $this;
    }

    public function isUsable(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->consentValidUntil !== null
            && $this->consentValidUntil > new \DateTimeImmutable();
    }

    public function needsConsentRefresh(): bool
    {
        if ($this->consentValidUntil === null) {
            return false;
        }
        $sevenDaysFromNow = new \DateTimeImmutable('+7 days');
        return $this->status === self::STATUS_ACTIVE
            && $this->consentValidUntil <= $sevenDaysFromNow;
    }

    public function getMaskedIban(): string
    {
        return $this->ibanCountryCode . '••••••••' . $this->ibanLastFour;
    }
}
