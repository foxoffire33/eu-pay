<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\P2PTransferRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Peer-to-peer transfer between EU Pay users, or to external bank accounts.
 *
 * Internal P2P: instant book transfer via PSD2 bank.
 * External P2P: SEPA Credit Transfer to any EU IBAN (Rabobank, ING, etc.).
 */
#[ORM\Entity(repositoryClass: P2PTransferRepository::class)]
#[ORM\Table(name: 'p2p_transfers')]
#[ORM\Index(columns: ['sender_id', 'created_at'], name: 'idx_p2p_sender_date')]
#[ORM\Index(columns: ['recipient_id'], name: 'idx_p2p_recipient')]
#[ORM\Index(columns: ['status'], name: 'idx_p2p_status')]
class P2PTransfer
{
    public const STATUS_INITIATED = 'initiated';
    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    public const TYPE_INTERNAL = 'internal';   // EU Pay → EU Pay (instant)
    public const TYPE_EXTERNAL = 'external';   // EU Pay → external IBAN (SEPA)

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    /** Sender (always an EU Pay user) */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $sender;

    /** Recipient EU Pay user (null for external transfers) */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $recipient = null;

    /** For external: encrypted recipient IBAN */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $encryptedRecipientIban = null;

    /** For external: encrypted recipient name */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $encryptedRecipientName = null;

    /** Blind index of recipient IBAN for lookup (HMAC-SHA256) */
    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $recipientIbanIndex = null;

    /** Blind index of recipient email for internal lookup */
    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $recipientEmailIndex = null;

    /** Transfer type: internal (EU Pay→EU Pay) or external (EU Pay→IBAN) */
    #[ORM\Column(type: 'string', length: 10)]
    private string $type;

    /** Amount in EUR cents */
    #[ORM\Column(type: 'integer')]
    private int $amountCents;

    #[ORM\Column(type: 'string', length: 3, options: ['default' => 'EUR'])]
    private string $currency = 'EUR';

    /** Encrypted message/note to recipient */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $encryptedMessage = null;

    /** Remittance reference on bank statement */
    #[ORM\Column(type: 'string', length: 140)]
    private string $reference;

    /** Recipient bank BIC for external transfers */
    #[ORM\Column(type: 'string', length: 11, nullable: true)]
    private ?string $recipientBic = null;

    /** PSD2 bank transaction ID for sender's debit */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $externalDebitTransactionId = null;

    /** PSD2 bank transaction ID for recipient's credit (internal only) */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $externalCreditTransactionId = null;

    /** PSD2 payment ID for external SEPA transfers */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $externalPaymentId = null;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = self::STATUS_INITIATED;

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
        $this->reference = 'EUPAY-P2P-' . strtoupper(substr($this->id->toRfc4122(), 0, 8));
    }

    // ── Getters / Setters ───────────────────────────────

    public function getId(): Uuid { return $this->id; }

    public function getSender(): User { return $this->sender; }
    public function setSender(User $user): self { $this->sender = $user; return $this; }

    public function getRecipient(): ?User { return $this->recipient; }
    public function setRecipient(?User $user): self { $this->recipient = $user; return $this; }

    public function getEncryptedRecipientIban(): ?string { return $this->encryptedRecipientIban; }
    public function setEncryptedRecipientIban(?string $blob): self { $this->encryptedRecipientIban = $blob; return $this; }

    public function getEncryptedRecipientName(): ?string { return $this->encryptedRecipientName; }
    public function setEncryptedRecipientName(?string $blob): self { $this->encryptedRecipientName = $blob; return $this; }

    public function getRecipientIbanIndex(): ?string { return $this->recipientIbanIndex; }
    public function setRecipientIbanIndex(?string $idx): self { $this->recipientIbanIndex = $idx; return $this; }

    public function getRecipientEmailIndex(): ?string { return $this->recipientEmailIndex; }
    public function setRecipientEmailIndex(?string $idx): self { $this->recipientEmailIndex = $idx; return $this; }

    public function getType(): string { return $this->type; }
    public function setType(string $type): self { $this->type = $type; return $this; }

    public function getAmountCents(): int { return $this->amountCents; }
    public function setAmountCents(int $cents): self { $this->amountCents = $cents; return $this; }

    public function getAmountEur(): string
    {
        return number_format($this->amountCents / 100, 2, '.', '');
    }

    public function getCurrency(): string { return $this->currency; }

    public function getEncryptedMessage(): ?string { return $this->encryptedMessage; }
    public function setEncryptedMessage(?string $blob): self { $this->encryptedMessage = $blob; return $this; }

    public function getReference(): string { return $this->reference; }

    public function getRecipientBic(): ?string { return $this->recipientBic; }
    public function setRecipientBic(?string $bic): self { $this->recipientBic = $bic; return $this; }

    public function getExternalDebitTransactionId(): ?string { return $this->externalDebitTransactionId; }
    public function setExternalDebitTransactionId(?string $id): self { $this->externalDebitTransactionId = $id; return $this; }

    public function getExternalCreditTransactionId(): ?string { return $this->externalCreditTransactionId; }
    public function setExternalCreditTransactionId(?string $id): self { $this->externalCreditTransactionId = $id; return $this; }

    public function getExternalPaymentId(): ?string { return $this->externalPaymentId; }
    public function setExternalPaymentId(?string $id): self { $this->externalPaymentId = $id; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getCompletedAt(): ?\DateTimeImmutable { return $this->completedAt; }
    public function getFailureReason(): ?string { return $this->failureReason; }

    public function isInternal(): bool { return $this->type === self::TYPE_INTERNAL; }
    public function isExternal(): bool { return $this->type === self::TYPE_EXTERNAL; }

    // ── State transitions ───────────────────────────────

    public function markPending(): self
    {
        $this->status = self::STATUS_PENDING;
        return $this;
    }

    public function markCompleted(): self
    {
        $this->status = self::STATUS_COMPLETED;
        $this->completedAt = new \DateTimeImmutable();
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
}
