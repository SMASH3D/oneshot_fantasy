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
use App\Enum\BracketType;
use App\Entity\Traits\EntityIdTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Doctrine ORM Entity strictly defining the Tournament structural schema and database relationships.
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
    order: ['startsAt' => 'ASC', 'name' => 'ASC'],
)]
#[ApiFilter(SearchFilter::class, properties: ['sportKey' => 'exact', 'status' => 'exact'])]
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
    #[Groups(['read', 'write'])]
    private string $name = '';

    #[ORM\Column(type: Types::STRING, unique: true, nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $slug = null;

    #[ORM\Column(type: Types::STRING)]
    #[Groups(['read', 'write'])]
    private string $sportKey = '';

    #[ORM\Column(type: Types::STRING, nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $statsAdapterKey = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $defaultScoringEngineKey = null;

    #[ORM\Column(type: Types::STRING, options: ['default' => 'UTC'])]
    #[Groups(['read', 'write'])]
    private string $timezone = 'UTC';

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    #[Groups(['read', 'write'])]
    private ?\DateTimeImmutable $startsAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    #[Groups(['read', 'write'])]
    private ?\DateTimeImmutable $endsAt = null;

    #[ORM\Column(type: Types::STRING, options: ['default' => 'draft'])]
    #[Groups(['read', 'write'])]
    private string $status = 'draft';

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON, options: ['default' => '{}'])]
    #[Groups(['read', 'write'])]
    private array $metadata = [];

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $cmsContent = null;

    #[ORM\Column(type: Types::STRING, length: 64, nullable: true, enumType: BracketType::class)]
    #[Groups(['read', 'write'])]
    private ?BracketType $bracketType = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    #[Groups(['read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    #[Groups(['read'])]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, Round> */
    #[ORM\OneToMany(targetEntity: Round::class, mappedBy: 'tournament', cascade: ['persist'], orphanRemoval: true)]
    private Collection $rounds;

    /** @var Collection<int, League> */
    #[ORM\OneToMany(targetEntity: League::class, mappedBy: 'tournament')]
    private Collection $leagues;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
        $this->rounds = new ArrayCollection();
        $this->leagues = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->name ?: '';
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

    public function getCmsContent(): ?string
    {
        return $this->cmsContent;
    }

    public function setCmsContent(?string $cmsContent): static
    {
        $this->cmsContent = $cmsContent;

        return $this;
    }

    public function getBracketType(): ?BracketType
    {
        return $this->bracketType;
    }

    public function setBracketType(BracketType|string|null $bracketType): static
    {
        if (is_string($bracketType)) {
            $bracketType = $bracketType !== '' ? BracketType::from($bracketType) : null;
        }

        $this->bracketType = $bracketType;

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

    /** @return Collection<int, League> */
    public function getLeagues(): Collection
    {
        return $this->leagues;
    }
}
