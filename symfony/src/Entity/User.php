<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use App\Entity\Traits\EntityIdTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Authenticated user entity. Implements Symfony UserInterface for the security layer.
 *
 * Available roles: ROLE_USER, ROLE_PRO, ROLE_API, ROLE_ADMIN.
 * ROLE_USER is always granted automatically (Symfony convention).
 */
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Patch(),
    ],
    normalizationContext: ['groups' => ['user:read']],
    denormalizationContext: ['groups' => ['user:write']],
    order: ['nickname' => 'ASC'],
    security: "is_granted('ROLE_ADMIN')",
)]
#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_users_created_at', columns: ['created_at'])]
#[UniqueEntity(fields: ['email'], message: 'An account with this email already exists.')]
#[UniqueEntity(fields: ['nickname'], message: 'This nickname is already taken.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    use EntityIdTrait;

    public const ROLE_USER  = 'ROLE_USER';
    public const ROLE_PRO   = 'ROLE_PRO';
    public const ROLE_API   = 'ROLE_API';
    public const ROLE_ADMIN = 'ROLE_ADMIN';

    #[ORM\Column(type: Types::STRING, unique: true)]
    #[Groups(['user:read', 'user:write'])]
    #[Assert\NotBlank]
    #[Assert\Email]
    private string $email = '';

    #[ORM\Column(type: Types::STRING, unique: true, length: 50)]
    #[Groups(['user:read', 'user:write'])]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 50)]
    #[Assert\Regex(pattern: '/^[a-zA-Z0-9_\-]+$/', message: 'Nickname may only contain letters, numbers, underscores and hyphens.')]
    private string $nickname = '';

    /** Hashed password — never serialized. */
    #[ORM\Column(type: Types::STRING)]
    private string $password = '';

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $roles = [];

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    #[Groups(['user:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    #[Groups(['user:read'])]
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
        $this->leagueMemberships   = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->nickname ?: $this->email;
    }

    // ── UserInterface ────────────────────────────────────────────────────────

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * Returns the roles granted to the user.
     * ROLE_USER is always included per Symfony convention.
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles   = $this->roles;
        $roles[] = self::ROLE_USER;

        return array_unique($roles);
    }

    public function eraseCredentials(): void
    {
        // No plain-text credentials stored on this entity.
    }

    // ── PasswordAuthenticatedUserInterface ───────────────────────────────────

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    // ── Getters / Setters ────────────────────────────────────────────────────

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getNickname(): string
    {
        return $this->nickname;
    }

    public function setNickname(string $nickname): static
    {
        $this->nickname = $nickname;

        return $this;
    }

    /** @param list<string> $roles */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

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
