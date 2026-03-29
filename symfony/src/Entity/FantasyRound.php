<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\EntityIdTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Fantasy scoring period for a league (maps to fantasy_rounds).
 */
#[ORM\Entity]
#[ORM\Table(name: 'fantasy_rounds')]
#[ORM\UniqueConstraint(name: 'uq_fantasy_rounds_league_order', columns: ['league_id', 'order_index'])]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_fantasy_rounds_league', columns: ['league_id'])]
#[ORM\Index(name: 'idx_fantasy_rounds_tournament_round', columns: ['tournament_round_id'])]
#[ORM\Index(name: 'idx_fantasy_rounds_locks_at', columns: ['locks_at'])]
class FantasyRound
{
    use EntityIdTrait;

    #[ORM\ManyToOne(targetEntity: League::class, inversedBy: 'fantasyRounds')]
    #[ORM\JoinColumn(name: 'league_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?League $league = null;

    #[ORM\ManyToOne(targetEntity: Round::class, inversedBy: 'fantasyRounds')]
    #[ORM\JoinColumn(name: 'tournament_round_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Round $tournamentRound = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $orderIndex = 0;

    #[ORM\Column(type: Types::STRING)]
    private string $name = '';

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $opensAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $locksAt = null;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON, options: ['default' => '{}'])]
    private array $metadata = [];

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, Lineup> */
    #[ORM\OneToMany(targetEntity: Lineup::class, mappedBy: 'fantasyRound', cascade: ['persist'], orphanRemoval: true)]
    private Collection $lineups;

    /** @var Collection<int, Score> */
    #[ORM\OneToMany(targetEntity: Score::class, mappedBy: 'fantasyRound', cascade: ['persist'], orphanRemoval: true)]
    private Collection $scores;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
        $this->lineups = new ArrayCollection();
        $this->scores = new ArrayCollection();
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

    public function getTournamentRound(): ?Round
    {
        return $this->tournamentRound;
    }

    public function setTournamentRound(?Round $tournamentRound): static
    {
        $this->tournamentRound = $tournamentRound;

        return $this;
    }

    public function getOrderIndex(): int
    {
        return $this->orderIndex;
    }

    public function setOrderIndex(int $orderIndex): static
    {
        $this->orderIndex = $orderIndex;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getOpensAt(): ?\DateTimeImmutable
    {
        return $this->opensAt;
    }

    public function setOpensAt(?\DateTimeImmutable $opensAt): static
    {
        $this->opensAt = $opensAt;

        return $this;
    }

    public function getLocksAt(): ?\DateTimeImmutable
    {
        return $this->locksAt;
    }

    public function setLocksAt(?\DateTimeImmutable $locksAt): static
    {
        $this->locksAt = $locksAt;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /** @param array<string, mixed> $metadata */
    public function setMetadata(array $metadata): static
    {
        $this->metadata = $metadata;

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

    /** @return Collection<int, Lineup> */
    public function getLineups(): Collection
    {
        return $this->lineups;
    }

    public function addLineup(Lineup $lineup): static
    {
        if (!$this->lineups->contains($lineup)) {
            $this->lineups->add($lineup);
            $lineup->setFantasyRound($this);
        }

        return $this;
    }

    /** @return Collection<int, Score> */
    public function getScores(): Collection
    {
        return $this->scores;
    }

    public function addScore(Score $score): static
    {
        if (!$this->scores->contains($score)) {
            $this->scores->add($score);
            $score->setFantasyRound($this);
        }

        return $this;
    }
}
