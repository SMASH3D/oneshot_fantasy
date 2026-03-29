<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\EntityIdTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Append-only: participant consumed by a membership in a fantasy round (supports use-once rules).
 */
#[ORM\Entity]
#[ORM\Table(name: 'usage_ledger_entries')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_usage_ledger_league_member_participant', columns: ['league_id', 'league_membership_id', 'participant_id'])]
#[ORM\Index(name: 'idx_usage_ledger_round', columns: ['fantasy_round_id'])]
#[ORM\Index(name: 'idx_usage_ledger_league_round', columns: ['league_id', 'fantasy_round_id'])]
class UsageLedgerEntry
{
    use EntityIdTrait;

    #[ORM\ManyToOne(targetEntity: League::class)]
    #[ORM\JoinColumn(name: 'league_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?League $league = null;

    #[ORM\ManyToOne(targetEntity: LeagueMembership::class)]
    #[ORM\JoinColumn(name: 'league_membership_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?LeagueMembership $leagueMembership = null;

    #[ORM\ManyToOne(targetEntity: Participant::class)]
    #[ORM\JoinColumn(name: 'participant_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Participant $participant = null;

    #[ORM\ManyToOne(targetEntity: FantasyRound::class)]
    #[ORM\JoinColumn(name: 'fantasy_round_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?FantasyRound $fantasyRound = null;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON, options: ['default' => '{}'])]
    private array $context = [];

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
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

    public function getLeagueMembership(): ?LeagueMembership
    {
        return $this->leagueMembership;
    }

    public function setLeagueMembership(?LeagueMembership $leagueMembership): static
    {
        $this->leagueMembership = $leagueMembership;

        return $this;
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

    public function getFantasyRound(): ?FantasyRound
    {
        return $this->fantasyRound;
    }

    public function setFantasyRound(?FantasyRound $fantasyRound): static
    {
        $this->fantasyRound = $fantasyRound;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getContext(): array
    {
        return $this->context;
    }

    /** @param array<string, mixed> $context */
    public function setContext(array $context): static
    {
        $this->context = $context;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
