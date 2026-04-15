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
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Stores pre-calculated aggregated scores per participant, config hash, and time scope.
 */
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
    ],
    normalizationContext: ['groups' => ['read']],
)]
#[ApiFilter(SearchFilter::class, properties: ['scoreConfigHash' => 'exact', 'season' => 'exact', 'statScope' => 'exact'])]
#[ORM\Entity]
#[ORM\Table(name: 'participant_aggregated_scores')]
#[ORM\UniqueConstraint(name: 'uq_participant_score_config_season_scope', columns: ['participant_id', 'score_config_hash', 'season', 'stat_scope'])]
#[UniqueEntity(fields: ['participant', 'scoreConfigHash', 'season', 'statScope'], message: 'This participant score for the given config hash, season, and scope already exists.')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_participant_scores_hash', columns: ['score_config_hash'])]
#[ORM\Index(name: 'idx_participant_scores_season', columns: ['season'])]
class ParticipantAggregatedScore
{
    use EntityIdTrait;

    #[ORM\ManyToOne(targetEntity: Participant::class, inversedBy: 'aggregatedScores')]
    #[ORM\JoinColumn(name: 'participant_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['read'])]
    private ?Participant $participant = null;

    #[ORM\Column(name: 'score_config_hash', type: Types::STRING, length: 64)]
    #[Groups(['read'])]
    private string $scoreConfigHash = '';

    #[ORM\Column(type: Types::STRING, length: 64)]
    #[Groups(['read'])]
    private string $season = '';

    #[ORM\Column(name: 'stat_scope', type: Types::STRING, length: 32, options: ['default' => 'season'])]
    #[Groups(['read'])]
    private string $statScope = 'season';

    #[ORM\Column(type: Types::FLOAT)]
    #[Groups(['read'])]
    private float $totalScore = 0.0;

    /** @var array<string, float> */
    #[ORM\Column(type: Types::JSON)]
    #[Groups(['read'])]
    private array $breakdown = [];

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

    public function getParticipant(): ?Participant
    {
        return $this->participant;
    }

    public function setParticipant(?Participant $participant): static
    {
        $this->participant = $participant;

        return $this;
    }

    public function getScoreConfigHash(): string
    {
        return $this->scoreConfigHash;
    }

    public function setScoreConfigHash(string $scoreConfigHash): static
    {
        $this->scoreConfigHash = $scoreConfigHash;

        return $this;
    }

    public function getSeason(): string
    {
        return $this->season;
    }

    public function setSeason(string $season): static
    {
        $this->season = $season;

        return $this;
    }

    public function getStatScope(): string
    {
        return $this->statScope;
    }

    public function setStatScope(string $statScope): static
    {
        $this->statScope = $statScope;

        return $this;
    }

    public function getTotalScore(): float
    {
        return $this->totalScore;
    }

    public function setTotalScore(float $totalScore): static
    {
        $this->totalScore = $totalScore;

        return $this;
    }

    /** @return array<string, float> */
    public function getBreakdown(): array
    {
        return $this->breakdown;
    }

    /** @param array<string, float> $breakdown */
    public function setBreakdown(array $breakdown): static
    {
        $this->breakdown = $breakdown;

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

    #[ORM\PreUpdate]
    public function touchUpdatedAt(): static
    {
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }
}
