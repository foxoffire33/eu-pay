<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CardRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\UuidV6;

#[ORM\Entity(repositoryClass: CardRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Card
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidV6 $id;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'cards')]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $externalCardId = null;

    /** bank account this card belongs to */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $externalAccountId = null;

    /** VIRTUAL or PHYSICAL */
    #[ORM\Column(length: 20)]
    private string $type = 'VIRTUAL';

    /** INACTIVE, ACTIVE, BLOCKED, CLOSED */
    #[ORM\Column(length: 20)]
    private string $status = 'INACTIVE';

    /** VISA, MASTERCARD, MAESTRO */
    #[ORM\Column(length: 20)]
    private string $scheme = 'VISA';

    #[ORM\Column(length: 4, nullable: true)]
    private ?string $lastFourDigits = null;

    #[ORM\Column(length: 7, nullable: true)]
    private ?string $expiryDate = null;

    /** @var Collection<int, HceToken> */
    #[ORM\OneToMany(targetEntity: HceToken::class, mappedBy: 'card', cascade: ['persist'])]
    private Collection $hceTokens;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id = new UuidV6();
        $this->hceTokens = new ArrayCollection();
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

    public function getExternalCardId(): ?string { return $this->externalCardId; }
    public function setExternalCardId(?string $v): static { $this->externalCardId = $v; return $this; }

    public function getExternalAccountId(): ?string { return $this->externalAccountId; }
    public function setExternalAccountId(?string $v): static { $this->externalAccountId = $v; return $this; }

    public function getType(): string { return $this->type; }
    public function setType(string $v): static { $this->type = $v; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): static { $this->status = $v; return $this; }

    public function getScheme(): string { return $this->scheme; }
    public function setScheme(string $v): static { $this->scheme = $v; return $this; }

    public function getLastFourDigits(): ?string { return $this->lastFourDigits; }
    public function setLastFourDigits(?string $v): static { $this->lastFourDigits = $v; return $this; }

    public function getExpiryDate(): ?string { return $this->expiryDate; }
    public function setExpiryDate(?string $v): static { $this->expiryDate = $v; return $this; }

    public function isActive(): bool { return $this->status === 'ACTIVE'; }

    /** @return Collection<int, HceToken> */
    public function getHceTokens(): Collection { return $this->hceTokens; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
}
