<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Entity\Traits\EntityIdTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Doctrine ORM Entity strictly defining the User structural schema and database relationships.
 */
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(),
        new Patch(),
    ],
    normalizationContext: ['groups' => ['read']],
    denormalizationContext: ['groups' => ['write']],
    order: ['displayName' => 'ASC'],
)]
#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_users_created_at', columns: ['created_at'])]
class User
{
    use EntityIdTrait;

    #[ORM\Column(type: Types::STRING, unique: true, nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $email = null;

    #[ORM\Column(type: Types::STRING)]
    #[Groups(['read', 'write'])]
    private string $displayName = '';

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    #[Groups(['read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    #[Groups(['read'])]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, League> */
    #[ORM\OneToMany(targetEntity: League::class, mappedBy: 'commissioner')]
    private Collection $commissionedLeagues;

    /** @var Collection<int, LeagueMembership> */
    #[ORM\OneToMany(targetEntity: LeagueMembership::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $leagueMemberships;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
        $this->commissionedLeagues = new ArrayCollection();
        $this->leagueMemberships = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->displayName ?: ($this->email ?: '');
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): static
    {
        $this->displayName = $displayName;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touchUpdatedAt(): static
    {
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    /** @return Collection<int, League> */
    public function getCommissionedLeagues(): Collection
    {
        return $this->commissionedLeagues;
    }

    /** @return Collection<int, LeagueMembership> */
    public function getLeagueMemberships(): Collection
    {
        return $this->leagueMemberships;
    }
}
