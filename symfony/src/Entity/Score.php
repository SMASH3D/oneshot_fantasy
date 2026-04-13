<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\Traits\EntityIdTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Aggregated fantasy points for one membership in one fantasy round (maps to round_scores).
 */
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
    ],
    normalizationContext: ['groups' => ['read']],
    order: ['points' => 'DESC'],
)]
#[ApiFilter(SearchFilter::class, properties: ['fantasyRound' => 'exact', 'leagueMembership' => 'exact'])]
#[ORM\Entity]
#[ORM\Table(name: 'round_scores')]
#[ORM\UniqueConstraint(name: 'uq_round_scores', columns: ['fantasy_round_id', 'league_membership_id'])]
#[ORM\Index(name: 'idx_round_scores_round', columns: ['fantasy_round_id'])]
#[ORM\Index(name: 'idx_round_scores_membership', columns: ['league_membership_id'])]
#[ORM\Index(name: 'idx_round_scores_points', columns: ['fantasy_round_id', 'points'])]
class Score
{
    use EntityIdTrait;

    #[ORM\ManyToOne(targetEntity: FantasyRound::class, inversedBy: 'scores')]
    #[ORM\JoinColumn(name: 'fantasy_round_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['read'])]
    private ?FantasyRound $fantasyRound = null;

    #[ORM\ManyToOne(targetEntity: LeagueMembership::class, inversedBy: 'scores')]
    #[ORM\JoinColumn(name: 'league_membership_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['read'])]
    private ?LeagueMembership $leagueMembership = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
    #[Groups(['read'])]
    private string $points = '0.0000';

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON, options: ['default' => '{}'])]
    #[Groups(['read'])]
    private array $breakdown = [];

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    #[Groups(['read'])]
    private \DateTimeImmutable $computedAt;

    public function __construct()
    {
        $this->computedAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return 'Score' . ($this->leagueMembership ? ' - ' . ((string) $this->leagueMembership) : '');
    }

    public function getFantasyRound(): ?FantasyRound
    {
        return $this->fantasyRound;
    }

    public function setFantasyRound(?FantasyRound $fantasyRound): static
    {
        $this->fantasyRound = $fantasyRound;

        return $this;
    }

    public function getLeagueMembership(): ?LeagueMembership
    {
        return $this->leagueMembership;
    }

    public function setLeagueMembership(?LeagueMembership $leagueMembership): static
    {
        $this->leagueMembership = $leagueMembership;

        return $this;
    }

    public function getPoints(): string
    {
        return $this->points;
    }

    public function setPoints(string $points): static
    {
        $this->points = $points;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getBreakdown(): array
    {
        return $this->breakdown;
    }

    /** @param array<string, mixed> $breakdown */
    public function setBreakdown(array $breakdown): static
    {
        $this->breakdown = $breakdown;

        return $this;
    }

    public function getComputedAt(): \DateTimeImmutable
    {
        return $this->computedAt;
    }

    public function setComputedAt(\DateTimeImmutable $computedAt): static
    {
        $this->computedAt = $computedAt;

        return $this;
    }
}
