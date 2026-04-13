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
 * Doctrine ORM Entity strictly defining the ParticipantGameStat structural schema and database relationships.
 */
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
    ],
    normalizationContext: ['groups' => ['read']],
)]
#[ApiFilter(SearchFilter::class, properties: ['participant' => 'exact', 'game' => 'exact', 'statDefinition' => 'exact'])]
#[ORM\Entity]
#[ORM\Table(name: 'participant_game_stats')]
#[ORM\UniqueConstraint(name: 'uq_participant_game_stats', columns: ['participant_id', 'game_id', 'stat_definition_id'])]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_participant_game_stats_participant', columns: ['participant_id'])]
#[ORM\Index(name: 'idx_participant_game_stats_game', columns: ['game_id'])]
class ParticipantGameStat
{
    use EntityIdTrait;

    #[ORM\ManyToOne(targetEntity: Participant::class)]
    #[ORM\JoinColumn(name: 'participant_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['read'])]
    private ?Participant $participant = null;

    #[ORM\ManyToOne(targetEntity: Game::class)]
    #[ORM\JoinColumn(name: 'game_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['read'])]
    private ?Game $game = null;

    #[ORM\ManyToOne(targetEntity: StatDefinition::class)]
    #[ORM\JoinColumn(name: 'stat_definition_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    #[Groups(['read'])]
    private ?StatDefinition $statDefinition = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    #[Groups(['read'])]
    private ?float $value = null;

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
        return 'Game Stat for ' . ($this->participant ? (string) $this->participant : 'Participant');
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

    public function getGame(): ?Game
    {
        return $this->game;
    }

    public function setGame(?Game $game): static
    {
        $this->game = $game;

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

    public function getValue(): ?float
    {
        return $this->value;
    }

    public function setValue(?float $value): static
    {
        $this->value = $value;

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
