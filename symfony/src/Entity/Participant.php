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
 * Sport-agnostic roster entry: a player or team in a given sport (identified by external_id + sport).
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
#[ApiFilter(SearchFilter::class, properties: ['sport' => 'exact', 'type' => 'exact', 'name' => 'partial', 'teamName' => 'partial'])]
#[ORM\Entity]
#[ORM\Table(name: 'participants')]
#[ORM\UniqueConstraint(name: 'uq_participants_external_id_sport', columns: ['external_id', 'sport'])]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_participants_external_id', columns: ['external_id'])]
#[ORM\Index(name: 'idx_participants_sport', columns: ['sport'])]
class Participant
{
    use EntityIdTrait;

    #[ORM\Column(name: 'external_id', type: Types::STRING, length: 255)]
    #[Groups(['read', 'write'])]
    private string $externalId = '';

    #[ORM\Column(type: Types::STRING, length: 512)]
    #[Groups(['read', 'write'])]
    private string $name = '';

    #[ORM\Column(type: Types::STRING, length: 64)]
    #[Groups(['read', 'write'])]
    private string $sport = '';

    /** Discriminator: "player" or "team" (and other sport-specific values if needed). */
    #[ORM\Column(name: 'type', type: Types::STRING, length: 32)]
    #[Groups(['read', 'write'])]
    private string $type = 'player';

    #[ORM\Column(type: Types::STRING, length: 512, nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $teamName = null;

    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $position = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['read', 'write'])]
    private ?array $metadata = null;

    #[ORM\ManyToOne(targetEntity: Team::class)]
    #[ORM\JoinColumn(name: 'team_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['read', 'write'])]
    private ?Team $team = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $injuryStatus = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    #[Groups(['read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    #[Groups(['read'])]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, ParticipantStat> */
    #[ORM\OneToMany(targetEntity: ParticipantStat::class, mappedBy: 'participant', cascade: ['persist'], orphanRemoval: true)]
    private Collection $stats;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
        $this->stats = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->name ?: ($this->teamName ?: 'Participant');
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    public function setExternalId(string $externalId): static
    {
        $this->externalId = $externalId;

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

    public function getSport(): string
    {
        return $this->sport;
    }

    public function setSport(string $sport): static
    {
        $this->sport = $sport;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getTeamName(): ?string
    {
        return $this->teamName;
    }

    public function setTeamName(?string $teamName): static
    {
        $this->teamName = $teamName;

        return $this;
    }

    public function getPosition(): ?string
    {
        return $this->position;
    }

    public function setPosition(?string $position): static
    {
        $this->position = $position;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    /** @param array<string, mixed>|null $metadata */
    public function setMetadata(?array $metadata): static
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

    /** @return Collection<int, ParticipantStat> */
    public function getStats(): Collection
    {
        return $this->stats;
    }

    public function getTeam(): ?Team
    {
        return $this->team;
    }

    public function setTeam(?Team $team): static
    {
        $this->team = $team;

        return $this;
    }

    public function getInjuryStatus(): ?string
    {
        return $this->injuryStatus;
    }

    public function setInjuryStatus(?string $injuryStatus): static
    {
        $this->injuryStatus = $injuryStatus;

        return $this;
    }
}
