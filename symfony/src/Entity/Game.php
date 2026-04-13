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
    order: ['date' => 'ASC'],
)]
#[ApiFilter(SearchFilter::class, properties: ['tournament' => 'exact', 'tournamentRound' => 'exact', 'status' => 'exact', 'externalId' => 'exact'])]
#[ORM\Entity]
#[ORM\Table(name: 'games')]
#[ORM\UniqueConstraint(name: 'uq_games_external_id', columns: ['external_id'])]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_games_tournament_round', columns: ['tournament_round_id'])]
#[ORM\Index(name: 'idx_games_external_id', columns: ['external_id'])]
class Game
{
    use EntityIdTrait;

    #[ORM\ManyToOne(targetEntity: Tournament::class)]
    #[ORM\JoinColumn(name: 'tournament_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['read'])]
    private ?Tournament $tournament = null;

    #[ORM\ManyToOne(targetEntity: Round::class)]
    #[ORM\JoinColumn(name: 'tournament_round_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['read'])]
    private ?Round $tournamentRound = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    #[Groups(['read'])]
    private ?\DateTimeImmutable $date = null;

    #[ORM\ManyToOne(targetEntity: Team::class)]
    #[ORM\JoinColumn(name: 'home_team_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['read'])]
    private ?Team $homeTeam = null;

    #[ORM\ManyToOne(targetEntity: Team::class)]
    #[ORM\JoinColumn(name: 'away_team_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['read'])]
    private ?Team $awayTeam = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Groups(['read'])]
    private ?int $homeScore = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Groups(['read'])]
    private ?int $awayScore = null;

    #[ORM\ManyToOne(targetEntity: Team::class)]
    #[ORM\JoinColumn(name: 'winner_team_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['read'])]
    private ?Team $winnerTeam = null;

    #[ORM\Column(type: Types::STRING, length: 64, options: ['default' => 'scheduled'])]
    #[Groups(['read'])]
    private string $status = 'scheduled';

    #[ORM\Column(name: 'external_id', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['read'])]
    private ?string $externalId = null;

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
        return $this->homeTeam && $this->awayTeam 
            ? ((string) $this->awayTeam . ' @ ' . (string) $this->homeTeam) 
            : 'Game';
    }

    public function getTournament(): ?Tournament
    {
        return $this->tournament;
    }

    public function setTournament(?Tournament $tournament): static
    {
        $this->tournament = $tournament;

        return $this;
    }

    public function getTournamentRound(): ?Round
    {
        return $this->tournamentRound;
    }

    public function setTournamentRound(?Round $tournamentRound): static
    {
        $this->tournamentRound = $tournamentRound;

        return $this;
    }

    public function getDate(): ?\DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(?\DateTimeImmutable $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getHomeTeam(): ?Team
    {
        return $this->homeTeam;
    }

    public function setHomeTeam(?Team $homeTeam): static
    {
        $this->homeTeam = $homeTeam;

        return $this;
    }

    public function getAwayTeam(): ?Team
    {
        return $this->awayTeam;
    }

    public function setAwayTeam(?Team $awayTeam): static
    {
        $this->awayTeam = $awayTeam;

        return $this;
    }

    public function getHomeScore(): ?int
    {
        return $this->homeScore;
    }

    public function setHomeScore(?int $homeScore): static
    {
        $this->homeScore = $homeScore;

        return $this;
    }

    public function getAwayScore(): ?int
    {
        return $this->awayScore;
    }

    public function setAwayScore(?int $awayScore): static
    {
        $this->awayScore = $awayScore;

        return $this;
    }

    public function getWinnerTeam(): ?Team
    {
        return $this->winnerTeam;
    }

    public function setWinnerTeam(?Team $winnerTeam): static
    {
        $this->winnerTeam = $winnerTeam;

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

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(?string $externalId): static
    {
        $this->externalId = $externalId;

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
