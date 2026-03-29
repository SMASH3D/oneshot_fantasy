<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\EntityIdTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'tournaments')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_tournaments_sport_key', columns: ['sport_key'])]
#[ORM\Index(name: 'idx_tournaments_status', columns: ['status'])]
#[ORM\Index(name: 'idx_tournaments_starts_at', columns: ['starts_at'])]
class Tournament
{
    use EntityIdTrait;

    #[ORM\Column(type: Types::STRING)]
    private string $name = '';

    #[ORM\Column(type: Types::STRING, unique: true, nullable: true)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::STRING)]
    private string $sportKey = '';

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $statsAdapterKey = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $defaultScoringEngineKey = null;

    #[ORM\Column(type: Types::STRING, options: ['default' => 'UTC'])]
    private string $timezone = 'UTC';

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $startsAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $endsAt = null;

    #[ORM\Column(type: Types::STRING, options: ['default' => 'draft'])]
    private string $status = 'draft';

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON, options: ['default' => '{}'])]
    private array $metadata = [];

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, Round> */
    #[ORM\OneToMany(targetEntity: Round::class, mappedBy: 'tournament', cascade: ['persist'], orphanRemoval: true)]
    private Collection $rounds;

    /** @var Collection<int, Participant> */
    #[ORM\OneToMany(targetEntity: Participant::class, mappedBy: 'tournament', cascade: ['persist'], orphanRemoval: true)]
    private Collection $participants;

    /** @var Collection<int, League> */
    #[ORM\OneToMany(targetEntity: League::class, mappedBy: 'tournament')]
    private Collection $leagues;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
        $this->rounds = new ArrayCollection();
        $this->participants = new ArrayCollection();
        $this->leagues = new ArrayCollection();
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

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getSportKey(): string
    {
        return $this->sportKey;
    }

    public function setSportKey(string $sportKey): static
    {
        $this->sportKey = $sportKey;

        return $this;
    }

    public function getStatsAdapterKey(): ?string
    {
        return $this->statsAdapterKey;
    }

    public function setStatsAdapterKey(?string $statsAdapterKey): static
    {
        $this->statsAdapterKey = $statsAdapterKey;

        return $this;
    }

    public function getDefaultScoringEngineKey(): ?string
    {
        return $this->defaultScoringEngineKey;
    }

    public function setDefaultScoringEngineKey(?string $defaultScoringEngineKey): static
    {
        $this->defaultScoringEngineKey = $defaultScoringEngineKey;

        return $this;
    }

    public function getTimezone(): string
    {
        return $this->timezone;
    }

    public function setTimezone(string $timezone): static
    {
        $this->timezone = $timezone;

        return $this;
    }

    public function getStartsAt(): ?\DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function setStartsAt(?\DateTimeImmutable $startsAt): static
    {
        $this->startsAt = $startsAt;

        return $this;
    }

    public function getEndsAt(): ?\DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function setEndsAt(?\DateTimeImmutable $endsAt): static
    {
        $this->endsAt = $endsAt;

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

    /** @return Collection<int, Round> */
    public function getRounds(): Collection
    {
        return $this->rounds;
    }

    public function addRound(Round $round): static
    {
        if (!$this->rounds->contains($round)) {
            $this->rounds->add($round);
            $round->setTournament($this);
        }

        return $this;
    }

    /** @return Collection<int, Participant> */
    public function getParticipants(): Collection
    {
        return $this->participants;
    }

    public function addParticipant(Participant $participant): static
    {
        if (!$this->participants->contains($participant)) {
            $this->participants->add($participant);
            $participant->setTournament($this);
        }

        return $this;
    }

    /** @return Collection<int, League> */
    public function getLeagues(): Collection
    {
        return $this->leagues;
    }
}
