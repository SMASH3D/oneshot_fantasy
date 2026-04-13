<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\EntityIdTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Doctrine ORM Entity strictly defining the UsageConstraintPolicy structural schema and database relationships.
 */
#[ORM\Entity]
#[ORM\Table(name: 'usage_constraint_policies')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_usage_constraint_policies_league', columns: ['league_id'])]
class UsageConstraintPolicy
{
    use EntityIdTrait;

    public const TYPE_USE_ONCE_GLOBAL = 'USE_ONCE_GLOBAL';

    #[ORM\ManyToOne(targetEntity: League::class)]
    #[ORM\JoinColumn(name: 'league_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?League $league = null;

    #[ORM\Column(type: Types::STRING)]
    private string $constraintType = self::TYPE_USE_ONCE_GLOBAL;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON, options: ['default' => '{}'])]
    private array $parameters = [];

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getLeague(): ?League
    {
        return $this->league;
    }

    public function setLeague(?League $league): static
    {
        $this->league = $league;

        return $this;
    }

    public function getConstraintType(): string
    {
        return $this->constraintType;
    }

    public function setConstraintType(string $constraintType): static
    {
        $this->constraintType = $constraintType;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /** @param array<string, mixed> $parameters */
    public function setParameters(array $parameters): static
    {
        $this->parameters = $parameters;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function touchUpdatedAt(): static
    {
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }
}
