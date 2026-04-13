<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Entity\Traits\EntityIdTrait;
use App\State\DraftPickCreateProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Doctrine ORM Entity strictly defining the DraftPick structural schema and database relationships.
 */
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(processor: DraftPickCreateProcessor::class),
    ],
    normalizationContext: ['groups' => ['read']],
    denormalizationContext: ['groups' => ['write']],
    order: ['pickIndex' => 'ASC'],
)]
#[ApiFilter(SearchFilter::class, properties: ['draftSession' => 'exact', 'leagueMembership' => 'exact'])]
#[ORM\Entity]
#[ORM\Table(name: 'draft_picks')]
#[ORM\UniqueConstraint(name: 'uq_draft_picks_session_index', columns: ['draft_session_id', 'pick_index'])]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_draft_picks_session', columns: ['draft_session_id'])]
#[ORM\Index(name: 'idx_draft_picks_membership', columns: ['league_membership_id'])]
#[ORM\Index(name: 'idx_draft_picks_participant', columns: ['participant_id'])]
class DraftPick
{
    use EntityIdTrait;

    #[ORM\ManyToOne(targetEntity: DraftSession::class, inversedBy: 'picks')]
    #[ORM\JoinColumn(name: 'draft_session_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['read', 'write'])]
    private ?DraftSession $draftSession = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['read'])]
    private int $pickIndex = 0;

    #[ORM\ManyToOne(targetEntity: LeagueMembership::class, inversedBy: 'draftPicks')]
    #[ORM\JoinColumn(name: 'league_membership_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    #[Groups(['read', 'write'])]
    private ?LeagueMembership $leagueMembership = null;

    #[ORM\ManyToOne(targetEntity: Participant::class)]
    #[ORM\JoinColumn(name: 'participant_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    #[Groups(['read', 'write'])]
    private ?Participant $participant = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    #[Groups(['read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getDraftSession(): ?DraftSession
    {
        return $this->draftSession;
    }

    public function setDraftSession(?DraftSession $draftSession): static
    {
        $this->draftSession = $draftSession;

        return $this;
    }

    public function getPickIndex(): int
    {
        return $this->pickIndex;
    }

    public function setPickIndex(int $pickIndex): static
    {
        $this->pickIndex = $pickIndex;

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
