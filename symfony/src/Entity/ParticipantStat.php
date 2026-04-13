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

#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
    ],
    normalizationContext: ['groups' => ['read']],
)]
#[ApiFilter(SearchFilter::class, properties: ['participant' => 'exact', 'statDefinition' => 'exact', 'season' => 'exact'])]
#[ORM\Entity]
#[ORM\Table(name: 'participant_stats')]
#[ORM\UniqueConstraint(name: 'uq_participant_stats', columns: ['participant_id', 'stat_definition_id', 'season'])]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_participant_stats_participant', columns: ['participant_id'])]
#[ORM\Index(name: 'idx_participant_stats_season', columns: ['season'])]
class ParticipantStat
{
    use EntityIdTrait;

    #[ORM\ManyToOne(targetEntity: Participant::class, inversedBy: 'stats')]
    #[ORM\JoinColumn(name: 'participant_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['read'])]
    private ?Participant $participant = null;

    #[ORM\ManyToOne(targetEntity: StatDefinition::class)]
    #[ORM\JoinColumn(name: 'stat_definition_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    #[Groups(['read'])]
    private ?StatDefinition $statDefinition = null;

    #[ORM\Column(type: Types::STRING, length: 64)]
    #[Groups(['read'])]
    private string $season = '';

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    #[Groups(['read'])]
    private ?float $totalValue = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    #[Groups(['read'])]
    private ?float $averageValue = null;

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

    public function getStatDefinition(): ?StatDefinition
    {
        return $this->statDefinition;
    }

    public function setStatDefinition(?StatDefinition $statDefinition): static
    {
        $this->statDefinition = $statDefinition;

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

    public function getTotalValue(): ?float
    {
        return $this->totalValue;
    }

    public function setTotalValue(?float $totalValue): static
    {
        $this->totalValue = $totalValue;

        return $this;
    }

    public function getAverageValue(): ?float
    {
        return $this->averageValue;
    }

    public function setAverageValue(?float $averageValue): static
    {
        $this->averageValue = $averageValue;

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
