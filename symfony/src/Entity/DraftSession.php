<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\EntityIdTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * One draft session per league (league_id is UNIQUE in the database).
 */
#[ORM\Entity]
#[ORM\Table(name: 'draft_sessions')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_draft_sessions_league', columns: ['league_id'])]
class DraftSession
{
    use EntityIdTrait;

    #[ORM\OneToOne(inversedBy: 'draftSession', targetEntity: League::class)]
    #[ORM\JoinColumn(name: 'league_id', referencedColumnName: 'id', nullable: false, unique: true, onDelete: 'CASCADE')]
    private ?League $league = null;

    #[ORM\Column(type: Types::STRING, options: ['default' => 'pending'])]
    private string $status = 'pending';

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON, options: ['default' => '{}'])]
    private array $config = [];

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, DraftPick> */
    #[ORM\OneToMany(targetEntity: DraftPick::class, mappedBy: 'draftSession', cascade: ['persist'], orphanRemoval: true)]
    private Collection $picks;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
        $this->picks = new ArrayCollection();
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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getConfig(): array
    {
        return $this->config;
    }

    /** @param array<string, mixed> $config */
    public function setConfig(array $config): static
    {
        $this->config = $config;

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

    /** @return Collection<int, DraftPick> */
    public function getPicks(): Collection
    {
        return $this->picks;
    }

    public function addPick(DraftPick $pick): static
    {
        if (!$this->picks->contains($pick)) {
            $this->picks->add($pick);
            $pick->setDraftSession($this);
        }

        return $this;
    }
}
