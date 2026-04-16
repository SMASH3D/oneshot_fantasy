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
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Tracks a team's participation and current status within a tournament.
 *
 * Status values:
 *   active     — still competing in the bracket
 *   eliminated — knocked out
 *   champion   — tournament winner
 *   playin     — in the play-in phase, not yet in the main bracket
 *
 * Populated and kept up-to-date by the Python ingester (import_nba_participations).
 * Can also be managed manually via the EasyAdmin interface.
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
    order: ['tournament' => 'ASC', 'seed' => 'ASC'],
)]
#[ApiFilter(SearchFilter::class, properties: ['tournament' => 'exact', 'team' => 'exact', 'status' => 'exact'])]
#[ORM\Entity]
#[ORM\Table(name: 'tournament_participations')]
#[ORM\UniqueConstraint(name: 'uq_tp_tournament_team', columns: ['tournament_id', 'team_id'])]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_tp_tournament', columns: ['tournament_id'])]
#[ORM\Index(name: 'idx_tp_team',       columns: ['team_id'])]
#[ORM\Index(name: 'idx_tp_status',     columns: ['status'])]
class TournamentParticipation
{
    use EntityIdTrait;

    #[ORM\ManyToOne(targetEntity: Tournament::class)]
    #[ORM\JoinColumn(name: 'tournament_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['read', 'write'])]
    private ?Tournament $tournament = null;

    #[ORM\ManyToOne(targetEntity: Team::class)]
    #[ORM\JoinColumn(name: 'team_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['read', 'write'])]
    private ?Team $team = null;

    /**
     * active | eliminated | champion | playin
     */
    #[ORM\Column(type: Types::STRING, length: 64, options: ['default' => 'active'])]
    #[Groups(['read', 'write'])]
    private string $status = 'active';

    /** Playoff seeding (1 = top seed). Null when not yet determined. */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Groups(['read', 'write'])]
    private ?int $seed = null;

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

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function __toString(): string
    {
        return ($this->team?->getName() ?? 'Team') . ' @ ' . ($this->tournament?->getName() ?? 'Tournament');
    }

    public function getTournament(): ?Tournament { return $this->tournament; }
    public function setTournament(?Tournament $tournament): static { $this->tournament = $tournament; return $this; }

    public function getTeam(): ?Team { return $this->team; }
    public function setTeam(?Team $team): static { $this->team = $team; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getSeed(): ?int { return $this->seed; }
    public function setSeed(?int $seed): static { $this->seed = $seed; return $this; }

    /** @return array<string, mixed> */
    public function getMetadata(): array { return $this->metadata; }
    /** @param array<string, mixed> $metadata */
    public function setMetadata(array $metadata): static { $this->metadata = $metadata; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    #[ORM\PreUpdate]
    public function touchUpdatedAt(): static { $this->updatedAt = new \DateTimeImmutable(); return $this; }
}
