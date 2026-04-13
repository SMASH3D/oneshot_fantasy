<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
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
 * Doctrine ORM Entity strictly defining the League structural schema and database relationships.
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
    order: ['name' => 'ASC'],
)]
#[ApiFilter(SearchFilter::class, properties: ['tournament' => 'exact', 'status' => 'exact'])]
#[ORM\Entity]
#[ORM\Table(name: 'fantasy_leagues')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_fantasy_leagues_tournament', columns: ['tournament_id'])]
#[ORM\Index(name: 'idx_fantasy_leagues_commissioner', columns: ['commissioner_user_id'])]
class League
{
    use EntityIdTrait;

    #[ORM\ManyToOne(targetEntity: Tournament::class, inversedBy: 'leagues')]
    #[ORM\JoinColumn(name: 'tournament_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    #[Groups(['read', 'write'])]
    private ?Tournament $tournament = null;

    #[ORM\Column(type: Types::STRING)]
    #[Groups(['read', 'write'])]
    private string $name = '';

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'commissionedLeagues')]
    #[ORM\JoinColumn(name: 'commissioner_user_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    #[Groups(['read', 'write'])]
    private ?User $commissioner = null;

    #[ORM\Column(type: Types::STRING, options: ['default' => 'forming'])]
    #[Groups(['read', 'write'])]
    private string $status = 'forming';

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON, options: ['default' => '{}'])]
    #[Groups(['read', 'write'])]
    private array $settings = [];

    /** @var list<array<string, mixed>> */
    #[ORM\Column(type: Types::JSON, options: ['default' => '[]'])]
    #[Groups(['read', 'write'])]
    private array $lineupTemplate = [];

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    #[Groups(['read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    #[Groups(['read'])]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, LeagueMembership> */
    #[ORM\OneToMany(targetEntity: LeagueMembership::class, mappedBy: 'league', cascade: ['persist'], orphanRemoval: true)]
    private Collection $memberships;

    /** @var Collection<int, FantasyRound> */
    #[ORM\OneToMany(targetEntity: FantasyRound::class, mappedBy: 'league', cascade: ['persist'], orphanRemoval: true)]
    private Collection $fantasyRounds;

    #[ORM\OneToOne(targetEntity: DraftSession::class, mappedBy: 'league', cascade: ['persist', 'remove'])]
    private ?DraftSession $draftSession = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
        $this->memberships = new ArrayCollection();
        $this->fantasyRounds = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->name ?: 'League';
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getCommissioner(): ?User
    {
        return $this->commissioner;
    }

    public function setCommissioner(?User $commissioner): static
    {
        $this->commissioner = $commissioner;

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
    public function getSettings(): array
    {
        return $this->settings;
    }

    /** @param array<string, mixed> $settings */
    public function setSettings(array $settings): static
    {
        $this->settings = $settings;

        return $this;
    }

    /** @return list<array<string, mixed>> */
    public function getLineupTemplate(): array
    {
        return $this->lineupTemplate;
    }

    /** @param list<array<string, mixed>> $lineupTemplate */
    public function setLineupTemplate(array $lineupTemplate): static
    {
        $this->lineupTemplate = $lineupTemplate;

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

    /** @return Collection<int, LeagueMembership> */
    public function getMemberships(): Collection
    {
        return $this->memberships;
    }

    /** @return Collection<int, FantasyRound> */
    public function getFantasyRounds(): Collection
    {
        return $this->fantasyRounds;
    }

    public function getDraftSession(): ?DraftSession
    {
        return $this->draftSession;
    }

    public function setDraftSession(?DraftSession $draftSession): static
    {
        $this->draftSession = $draftSession;

        return $this;
    }
}
