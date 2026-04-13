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
use App\State\LineupSubmitProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * One lineup submission per membership per fantasy round (maps to lineup_submissions).
 */
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(),
        new Patch(),
        new Post(
            uriTemplate: '/lineup_submissions/{id}/submit',
            input: false,
            processor: LineupSubmitProcessor::class,
            name: 'lineup_submit',
        ),
    ],
    normalizationContext: ['groups' => ['read']],
    denormalizationContext: ['groups' => ['write']],
)]
#[ApiFilter(SearchFilter::class, properties: ['fantasyRound' => 'exact', 'leagueMembership' => 'exact', 'status' => 'exact'])]
#[ORM\Entity]
#[ORM\Table(name: 'lineup_submissions')]
#[ORM\UniqueConstraint(name: 'uq_lineup_submissions', columns: ['fantasy_round_id', 'league_membership_id'])]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_lineup_submissions_round', columns: ['fantasy_round_id'])]
#[ORM\Index(name: 'idx_lineup_submissions_membership', columns: ['league_membership_id'])]
class Lineup
{
    use EntityIdTrait;

    #[ORM\ManyToOne(targetEntity: FantasyRound::class, inversedBy: 'lineups')]
    #[ORM\JoinColumn(name: 'fantasy_round_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['read', 'write'])]
    private ?FantasyRound $fantasyRound = null;

    #[ORM\ManyToOne(targetEntity: LeagueMembership::class, inversedBy: 'lineups')]
    #[ORM\JoinColumn(name: 'league_membership_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['read', 'write'])]
    private ?LeagueMembership $leagueMembership = null;

    #[ORM\Column(type: Types::STRING, options: ['default' => 'draft'])]
    #[Groups(['read'])]
    private string $status = 'draft';

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    #[Groups(['read'])]
    private ?\DateTimeImmutable $submittedAt = null;

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
        return 'Lineup' . ($this->leagueMembership ? ' - ' . ((string) $this->leagueMembership) : '');
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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getSubmittedAt(): ?\DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function setSubmittedAt(?\DateTimeImmutable $submittedAt): static
    {
        $this->submittedAt = $submittedAt;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /** @param array<string, mixed> $metadata */
    public function setMetadata(array $metadata): static
    {
        $this->metadata = $metadata;

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
}
