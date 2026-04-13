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
use App\State\DraftCompleteProcessor;
use App\State\DraftStartProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * One draft session per league (league_id is UNIQUE in the database).
 */
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Patch(
            denormalizationContext: ['groups' => ['draft_session:configure']],
        ),
        new Post(
            uriTemplate: '/draft_sessions/{id}/start',
            input: false,
            processor: DraftStartProcessor::class,
            name: 'draft_session_start',
        ),
        new Post(
            uriTemplate: '/draft_sessions/{id}/complete',
            input: false,
            processor: DraftCompleteProcessor::class,
            name: 'draft_session_complete',
        ),
    ],
    normalizationContext: ['groups' => ['read']],
    denormalizationContext: ['groups' => ['write']],
)]
#[ApiFilter(SearchFilter::class, properties: ['league' => 'exact', 'status' => 'exact'])]
#[ORM\Entity]
#[ORM\Table(name: 'draft_sessions')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_draft_sessions_league', columns: ['league_id'])]
class DraftSession
{
    use EntityIdTrait;

    #[ORM\OneToOne(inversedBy: 'draftSession', targetEntity: League::class)]
    #[ORM\JoinColumn(name: 'league_id', referencedColumnName: 'id', nullable: false, unique: true, onDelete: 'CASCADE')]
    #[Groups(['read', 'write'])]
    private ?League $league = null;

    #[ORM\Column(type: Types::STRING, options: ['default' => 'pending'])]
    #[Groups(['read'])]
    private string $status = 'pending';

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON, options: ['default' => '{}'])]
    #[Groups(['read', 'draft_session:configure'])]
    private array $config = [];

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    #[Groups(['read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    #[Groups(['read'])]
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

    public function __toString(): string
    {
        return $this->league ? $this->league->getName() . ' - Draft Session' : 'Draft Session';
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
