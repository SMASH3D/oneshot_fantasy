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
 * Doctrine ORM Entity defining the game score for a participant in a specific fantasy league.
 */
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
    ],
    normalizationContext: ['groups' => ['read']],
    order: ['createdAt' => 'DESC'],
)]
#[ApiFilter(SearchFilter::class, properties: ['participant' => 'exact', 'game' => 'exact', 'fantasyLeague' => 'exact'])]
#[ORM\Entity]
#[ORM\Table(name: 'participant_game_score')]
#[ORM\UniqueConstraint(name: 'uq_participant_game_score', columns: ['participant_id', 'game_id', 'fantasy_league_id'])]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_pgs_participant', columns: ['participant_id'])]
#[ORM\Index(name: 'idx_pgs_game', columns: ['game_id'])]
#[ORM\Index(name: 'idx_pgs_fantasy_league', columns: ['fantasy_league_id'])]
class ParticipantGameScore
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

    #[ORM\ManyToOne(targetEntity: League::class)]
    #[ORM\JoinColumn(name: 'fantasy_league_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['read'])]
    private ?League $fantasyLeague = null;

    #[ORM\Column(type: Types::FLOAT)]
    #[Groups(['read'])]
    private float $score = 0.0;

    /** @var array<string, mixed> */
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

    public function getGame(): ?Game
    {
        return $this->game;
    }

    public function setGame(?Game $game): static
    {
        $this->game = $game;

        return $this;
    }

    public function getFantasyLeague(): ?League
    {
        return $this->fantasyLeague;
    }

    public function setFantasyLeague(?League $fantasyLeague): static
    {
        $this->fantasyLeague = $fantasyLeague;

        return $this;
    }

    public function getScore(): float
    {
        return $this->score;
    }

    public function setScore(float $score): static
    {
        $this->score = $score;

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
