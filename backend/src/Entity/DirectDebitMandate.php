<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\DirectDebitMandateRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\UuidV6;

/**
 * SEPA Direct Debit (SDD / Euro-incasso) mandate.
 *
 * Authorizes EU Pay to pull funds from the user's linked bank account
 * to fund their virtual debit card. Required before any card transaction.
 *
 * SEPA SDD Core scheme — D+1 settlement, consumer protection (8-week refund).
 */
#[ORM\Entity(repositoryClass: DirectDebitMandateRepository::class)]
#[ORM\Table(name: 'direct_debit_mandates')]
#[ORM\Index(fields: ['user', 'status'], name: 'idx_sdd_user_status')]
#[ORM\Index(fields: ['mandateReference'], name: 'idx_sdd_mandate_ref')]
#[ORM\HasLifecycleCallbacks]
class DirectDebitMandate
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_REVOKED = 'revoked';
    public const STATUS_FAILED = 'failed';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidV6 $id;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'directDebitMandates')]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\ManyToOne(targetEntity: LinkedBankAccount::class)]
    #[ORM\JoinColumn(nullable: false)]
    private LinkedBankAccount $linkedBankAccount;

    /** Unique SEPA mandate reference (max 35 chars) */
    #[ORM\Column(length: 35, unique: true)]
    private string $mandateReference;

    /** EU Pay's SEPA Creditor Identifier */
    #[ORM\Column(length: 35)]
    private string $creditorId;

    /** Envelope-encrypted debtor IBAN */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $encryptedDebtorIban = null;

    /** When the user signed/consented to the SDD mandate */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $signedAt = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    /** Maximum amount per direct debit in cents (default €500) */
    #[ORM\Column(type: 'integer')]
    private int $maxAmountCents = 50000;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id = new UuidV6();
        $this->mandateReference = 'EUPAY-SDD-' . strtoupper(substr($this->id->toRfc4122(), 0, 8));
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

    public function getLinkedBankAccount(): LinkedBankAccount { return $this->linkedBankAccount; }
    public function setLinkedBankAccount(LinkedBankAccount $v): static { $this->linkedBankAccount = $v; return $this; }

    // ── Mandate data ──

    public function getMandateReference(): string { return $this->mandateReference; }

    public function getCreditorId(): string { return $this->creditorId; }
    public function setCreditorId(string $v): static { $this->creditorId = $v; return $this; }

    public function getEncryptedDebtorIban(): ?string { return $this->encryptedDebtorIban; }
    public function setEncryptedDebtorIban(?string $v): static { $this->encryptedDebtorIban = $v; return $this; }

    public function getSignedAt(): ?\DateTimeImmutable { return $this->signedAt; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): static { $this->status = $v; return $this; }

    public function getMaxAmountCents(): int { return $this->maxAmountCents; }
    public function setMaxAmountCents(int $v): static { $this->maxAmountCents = $v; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    // ── State transitions ──

    public function activate(): static
    {
        $this->status = self::STATUS_ACTIVE;
        $this->signedAt = new \DateTimeImmutable();
        return $this;
    }

    public function revoke(): static
    {
        $this->status = self::STATUS_REVOKED;
        $this->encryptedDebtorIban = null;
        return $this;
    }

    public function markFailed(): static
    {
        $this->status = self::STATUS_FAILED;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
