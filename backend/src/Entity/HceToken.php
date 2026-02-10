<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\HceTokenRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\UuidV6;

/**
 * Represents a device-bound HCE payment token.
 *
 * Contains the encrypted DPAN and session keys needed
 * by the Android HCE service to respond to POS terminal APDUs.
 */
#[ORM\Entity(repositoryClass: HceTokenRepository::class)]
#[ORM\HasLifecycleCallbacks]
class HceToken
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidV6 $id;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'hceTokens')]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Card::class, inversedBy: 'hceTokens')]
    #[ORM\JoinColumn(nullable: false)]
    private Card $card;

    #[ORM\Column(length: 100)]
    private string $externalCardId;

    /** SHA-256 hash of device hardware identifiers */
    #[ORM\Column(length: 64)]
    private string $deviceFingerprint;

    /** AES-256 encrypted Device PAN (token PAN replacing real card number) */
    #[ORM\Column(type: Types::TEXT)]
    private string $dpan;

    /** AES-256 encrypted session key for cryptogram generation */
    #[ORM\Column(type: Types::TEXT)]
    private string $sessionKey;

    /** Token reference ID at card issuer (Marqeta/Adyen) — links DPAN to issuer */
    #[ORM\Column(length: 200, nullable: true)]
    private ?string $tokenReferenceId = null;

    /** AES-256 encrypted EMV key material (ICC private key, certificates) */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $encryptedEmvKeys = null;

    /** Application Transaction Counter — incremented per tap */
    #[ORM\Column(type: Types::INTEGER)]
    private int $atc = 0;

    /** VISA, MASTERCARD */
    #[ORM\Column(length: 20)]
    private string $cardScheme = 'VISA';

    /** ACTIVE, SUSPENDED, DEACTIVATED */
    #[ORM\Column(length: 20)]
    private string $status = 'ACTIVE';

    #[ORM\Column(type: Types::SMALLINT)]
    private int $expiryMonth = 12;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $expiryYear = 2028;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id = new UuidV6();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->expiresAt = new \DateTimeImmutable('+5 minutes');
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): UuidV6 { return $this->id; }

    public function getUser(): User { return $this->user; }
    public function setUser(User $v): static { $this->user = $v; return $this; }

    public function getCard(): Card { return $this->card; }
    public function setCard(Card $v): static { $this->card = $v; return $this; }

    public function getExternalCardId(): string { return $this->externalCardId; }
    public function setExternalCardId(string $v): static { $this->externalCardId = $v; return $this; }

    public function getDeviceFingerprint(): string { return $this->deviceFingerprint; }
    public function setDeviceFingerprint(string $v): static { $this->deviceFingerprint = $v; return $this; }

    public function getDpan(): string { return $this->dpan; }
    public function setDpan(string $v): static { $this->dpan = $v; return $this; }

    public function getSessionKey(): string { return $this->sessionKey; }
    public function setSessionKey(string $v): static { $this->sessionKey = $v; return $this; }

    public function getTokenReferenceId(): ?string { return $this->tokenReferenceId; }
    public function setTokenReferenceId(?string $v): static { $this->tokenReferenceId = $v; return $this; }

    public function getEncryptedEmvKeys(): ?string { return $this->encryptedEmvKeys; }
    public function setEncryptedEmvKeys(?string $v): static { $this->encryptedEmvKeys = $v; return $this; }

    public function getAtc(): int { return $this->atc; }
    public function setAtc(int $v): static { $this->atc = $v; return $this; }

    public function getCardScheme(): string { return $this->cardScheme; }
    public function setCardScheme(string $v): static { $this->cardScheme = $v; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): static { $this->status = $v; return $this; }

    public function getExpiryMonth(): int { return $this->expiryMonth; }
    public function setExpiryMonth(int $v): static { $this->expiryMonth = $v; return $this; }

    public function getExpiryYear(): int { return $this->expiryYear; }
    public function setExpiryYear(int $v): static { $this->expiryYear = $v; return $this; }

    public function getExpiresAt(): \DateTimeImmutable { return $this->expiresAt; }
    public function setExpiresAt(\DateTimeImmutable $v): static { $this->expiresAt = $v; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    public function isActive(): bool { return $this->status === 'ACTIVE'; }
    public function isExpired(): bool { return $this->expiresAt < new \DateTimeImmutable(); }
}
