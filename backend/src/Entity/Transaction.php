<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TransactionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\UuidV6;

#[ORM\Entity(repositoryClass: TransactionRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Transaction
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidV6 $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $externalTransactionId = null;

    /** CARD_AUTHORIZATION, SEPA_TRANSFER, etc. */
    #[ORM\Column(length: 50)]
    private string $type = 'CARD_AUTHORIZATION';

    /** PENDING, COMPLETED, DECLINED, REVERSED */
    #[ORM\Column(length: 30)]
    private string $status = 'PENDING';

    #[ORM\Column(length: 20)]
    private string $amount = '0.00';

    #[ORM\Column(length: 3)]
    private string $currency = 'EUR';

    /** Envelope-encrypted merchant name (zero-knowledge) */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $encryptedMerchantName = null;

    /** Envelope-encrypted merchant city */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $encryptedMerchantCity = null;

    /** NFC, CHIP, ONLINE etc. */
    #[ORM\Column(length: 30, nullable: true)]
    private ?string $posEntryMode = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
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

    public function getId(): UuidV6 { return $this->id; }

    public function getUser(): User { return $this->user; }
    public function setUser(User $v): static { $this->user = $v; return $this; }

    public function getExternalTransactionId(): ?string { return $this->externalTransactionId; }
    public function setExternalTransactionId(?string $v): static { $this->externalTransactionId = $v; return $this; }

    public function getType(): string { return $this->type; }
    public function setType(string $v): static { $this->type = $v; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): static { $this->status = $v; return $this; }

    public function getAmount(): string { return $this->amount; }
    public function setAmount(string $v): static { $this->amount = $v; return $this; }

    public function getCurrency(): string { return $this->currency; }
    public function setCurrency(string $v): static { $this->currency = $v; return $this; }

    public function getEncryptedMerchantName(): ?string { return $this->encryptedMerchantName; }
    public function setEncryptedMerchantName(?string $v): static { $this->encryptedMerchantName = $v; return $this; }
    /** @deprecated Use getEncryptedMerchantName — backward compat for tests */
    public function getMerchantName(): ?string { return $this->encryptedMerchantName; }

    public function getEncryptedMerchantCity(): ?string { return $this->encryptedMerchantCity; }
    public function setEncryptedMerchantCity(?string $v): static { $this->encryptedMerchantCity = $v; return $this; }

    public function getPosEntryMode(): ?string { return $this->posEntryMode; }
    public function setPosEntryMode(?string $v): static { $this->posEntryMode = $v; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
}
