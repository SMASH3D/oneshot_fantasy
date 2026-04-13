<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
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
 * Bracket phase of a real-world tournament (maps to tournament_rounds).
 */
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(),
        new Patch(),
        new Delete(),
    ],
    normalizationContext: ['groups' => ['read']],
    denormalizationContext: ['groups' => ['write']],
    order: ['orderIndex' => 'ASC'],
)]
#[ApiFilter(SearchFilter::class, properties: ['tournament' => 'exact'])]
#[ORM\Entity]
#[ORM\Table(name: 'tournament_rounds')]
#[ORM\UniqueConstraint(name: 'uq_tournament_rounds_order', columns: ['tournament_id', 'order_index'])]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_tournament_rounds_tournament', columns: ['tournament_id'])]
class Round
{
    use EntityIdTrait;

    #[ORM\ManyToOne(targetEntity: Tournament::class, inversedBy: 'rounds')]
    #[ORM\JoinColumn(name: 'tournament_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['read', 'write'])]
    private ?Tournament $tournament = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['read', 'write'])]
    private int $orderIndex = 0;

    #[ORM\Column(type: Types::STRING)]
    #[Groups(['read', 'write'])]
    private string $name = '';

    #[ORM\Column(type: Types::STRING, nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $canonicalKey = null;

    #[ORM\ManyToOne(targetEntity: Team::class)]
    #[ORM\JoinColumn(name: 'home_team_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['read', 'write'])]
    private ?Team $homeTeam = null;

    #[ORM\ManyToOne(targetEntity: Team::class)]
    #[ORM\JoinColumn(name: 'away_team_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['read', 'write'])]
    private ?Team $awayTeam = null;

    #[ORM\Column(type: Types::STRING, length: 64, options: ['default' => 'pending'])]
    #[Groups(['read', 'write'])]
    private string $status = 'pending';

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON, options: ['default' => '{}'])]
    #[Groups(['read', 'write'])]
    private array $metadata = [];

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    #[Groups(['read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    #[Groups(['read'])]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, FantasyRound> */
    #[ORM\OneToMany(targetEntity: FantasyRound::class, mappedBy: 'tournamentRound')]
    private Collection $fantasyRounds;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
        $this->fantasyRounds = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->name ?: 'Round';
    }

    public function getTournament(): ?Tournament
    {
        return $this->tournament;
    }

    public function setTournament(?Tournament $tournament): static
    {
        $this->tournament = $tournament;

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

    public function getCanonicalKey(): ?string
    {
        return $this->canonicalKey;
    }

    public function setCanonicalKey(?string $canonicalKey): static
    {
        $this->canonicalKey = $canonicalKey;

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

    /** @return Collection<int, FantasyRound> */
    public function getFantasyRounds(): Collection
    {
        return $this->fantasyRounds;
    }

    public function getHomeTeam(): ?Team
    {
        return $this->homeTeam;
    }

    public function setHomeTeam(?Team $homeTeam): static
    {
        $this->homeTeam = $homeTeam;

        return $this;
    }

    public function getAwayTeam(): ?Team
    {
        return $this->awayTeam;
    }

    public function setAwayTeam(?Team $awayTeam): static
    {
        $this->awayTeam = $awayTeam;

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
}
