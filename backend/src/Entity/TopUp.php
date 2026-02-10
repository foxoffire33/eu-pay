<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TopUpRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Top-up transaction — funding an EU Pay bank account from an external bank.
 *
 * Flow: User initiates → PSD2 PISP redirect → SCA at bank → callback → settled.
 * Supports: SEPA Credit Transfer, iDEAL (Dutch instant).
 */
#[ORM\Entity(repositoryClass: TopUpRepository::class)]
#[ORM\Table(name: 'top_ups')]
#[ORM\Index(fields: ['user', 'status'], name: 'idx_topup_user_status')]
#[ORM\Index(fields: ['externalPaymentId'], name: 'idx_topup_external_id')]
class TopUp
{
    public const STATUS_INITIATED = 'initiated';     // Created, awaiting SCA
    public const STATUS_PENDING = 'pending';          // SCA done, awaiting settlement
    public const STATUS_COMPLETED = 'completed';      // Funds received
    public const STATUS_FAILED = 'failed';            // Rejected or expired
    public const STATUS_CANCELLED = 'cancelled';      // User cancelled at bank

    public const METHOD_SEPA = 'sepa_credit_transfer';
    public const METHOD_IDEAL = 'ideal';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    /** Amount in EUR cents (e.g., 1000 = €10.00) */
    #[ORM\Column(type: 'integer')]
    private int $amountCents;

    #[ORM\Column(type: 'string', length: 3, options: ['default' => 'EUR'])]
    private string $currency = 'EUR';

    /** Payment method: sepa_credit_transfer or ideal */
    #[ORM\Column(type: 'string', length: 30)]
    private string $method;

    /** Source bank BIC (e.g., RABONL2U) */
    #[ORM\Column(type: 'string', length: 11, nullable: true)]
    private ?string $sourceBic = null;

    /** Encrypted source IBAN (zero-knowledge: stored as encrypted blob) */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $encryptedSourceIban = null;

    /** PSD2 payment initiation ID from the ASPSP */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $externalPaymentId = null;

    /** Bank authorisation URL for SCA redirect */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $authorisationUrl = null;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = self::STATUS_INITIATED;

    /** PSD2 bank transaction ID once funds are credited */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $externalTransactionId = null;

    /** Remittance / reference shown on bank statement */
    #[ORM\Column(type: 'string', length: 140)]
    private string $reference;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $failureReason = null;

    public function __construct()
    {
        $this->id = Uuid::v6();
        $this->createdAt = new \DateTimeImmutable();
        $this->reference = 'EUPAY-' . strtoupper(substr($this->id->toRfc4122(), 0, 8));
    }

    // ── Getters / Setters ───────────────────────────────

    public function getId(): Uuid { return $this->id; }

    public function getUser(): User { return $this->user; }
    public function setUser(User $user): self { $this->user = $user; return $this; }

    public function getAmountCents(): int { return $this->amountCents; }
    public function setAmountCents(int $cents): self { $this->amountCents = $cents; return $this; }

    public function getAmountEur(): string
    {
        return number_format($this->amountCents / 100, 2, '.', '');
    }

    public function getCurrency(): string { return $this->currency; }

    public function getMethod(): string { return $this->method; }
    public function setMethod(string $method): self { $this->method = $method; return $this; }

    public function getSourceBic(): ?string { return $this->sourceBic; }
    public function setSourceBic(?string $bic): self { $this->sourceBic = $bic; return $this; }

    public function getEncryptedSourceIban(): ?string { return $this->encryptedSourceIban; }
    public function setEncryptedSourceIban(?string $blob): self { $this->encryptedSourceIban = $blob; return $this; }

    public function getExternalPaymentId(): ?string { return $this->externalPaymentId; }
    public function setExternalPaymentId(?string $id): self { $this->externalPaymentId = $id; return $this; }

    public function getAuthorisationUrl(): ?string { return $this->authorisationUrl; }
    public function setAuthorisationUrl(?string $url): self { $this->authorisationUrl = $url; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }

    public function getExternalTransactionId(): ?string { return $this->externalTransactionId; }
    public function setExternalTransactionId(?string $id): self { $this->externalTransactionId = $id; return $this; }

    public function getReference(): string { return $this->reference; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getCompletedAt(): ?\DateTimeImmutable { return $this->completedAt; }

    public function getFailureReason(): ?string { return $this->failureReason; }

    // ── State transitions ───────────────────────────────

    public function markPending(): self
    {
        $this->status = self::STATUS_PENDING;
        return $this;
    }

    public function markCompleted(?string $externalTransactionId = null): self
    {
        $this->status = self::STATUS_COMPLETED;
        $this->completedAt = new \DateTimeImmutable();
        $this->externalTransactionId = $externalTransactionId;
        return $this;
    }

    public function markFailed(string $reason): self
    {
        $this->status = self::STATUS_FAILED;
        $this->failureReason = $reason;
        return $this;
    }

    public function markCancelled(): self
    {
        $this->status = self::STATUS_CANCELLED;
        return $this;
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
        ], true);
    }
}
