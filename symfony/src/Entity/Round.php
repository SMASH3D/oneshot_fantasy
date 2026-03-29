<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\EntityIdTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Bracket phase of a real-world tournament (maps to tournament_rounds).
 */
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
    private ?Tournament $tournament = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $orderIndex = 0;

    #[ORM\Column(type: Types::STRING)]
    private string $name = '';

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $canonicalKey = null;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON, options: ['default' => '{}'])]
    private array $metadata = [];

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
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
}
