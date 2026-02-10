<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\WebAuthnCredentialRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\UuidV6;

#[ORM\Entity(repositoryClass: WebAuthnCredentialRepository::class)]
#[ORM\Table(name: 'webauthn_credentials')]
#[ORM\Index(name: 'idx_credential_id', fields: ['credentialId'])]
#[ORM\HasLifecycleCallbacks]
class WebAuthnCredential
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidV6 $id;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'webAuthnCredentials')]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(type: Types::TEXT)]
    private string $credentialId;

    #[ORM\Column(type: Types::TEXT)]
    private string $credentialPublicKey;

    #[ORM\Column(type: Types::INTEGER)]
    private int $signCount = 0;

    #[ORM\Column(length: 36)]
    private string $aaguid = '';

    #[ORM\Column(length: 255)]
    private string $deviceName = '';

    #[ORM\Column(type: Types::JSON)]
    private array $transports = [];

    #[ORM\Column(length: 20)]
    private string $attestationType = 'none';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

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
    public function setUser(User $user): static { $this->user = $user; return $this; }

    public function getCredentialId(): string { return $this->credentialId; }
    public function setCredentialId(string $v): static { $this->credentialId = $v; return $this; }

    public function getCredentialPublicKey(): string { return $this->credentialPublicKey; }
    public function setCredentialPublicKey(string $v): static { $this->credentialPublicKey = $v; return $this; }

    public function getSignCount(): int { return $this->signCount; }
    public function setSignCount(int $v): static { $this->signCount = $v; return $this; }

    public function getAaguid(): string { return $this->aaguid; }
    public function setAaguid(string $v): static { $this->aaguid = $v; return $this; }

    public function getDeviceName(): string { return $this->deviceName; }
    public function setDeviceName(string $v): static { $this->deviceName = $v; return $this; }

    public function getTransports(): array { return $this->transports; }
    public function setTransports(array $v): static { $this->transports = $v; return $this; }

    public function getAttestationType(): string { return $this->attestationType; }
    public function setAttestationType(string $v): static { $this->attestationType = $v; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    public function getLastUsedAt(): ?\DateTimeImmutable { return $this->lastUsedAt; }
    public function setLastUsedAt(?\DateTimeImmutable $v): static { $this->lastUsedAt = $v; return $this; }

    public function markUsed(): static
    {
        $this->lastUsedAt = new \DateTimeImmutable();
        return $this;
    }
}
