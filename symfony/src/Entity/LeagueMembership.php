<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Entity\Traits\EntityIdTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * User membership in a fantasy league (required FK for DraftPick, Lineup, Score).
 */
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(),
        new Patch(),
        new Delete(),
    ],
    normalizationContext: ['groups' => ['read']],
    denormalizationContext: ['groups' => ['write']],
)]
#[ApiFilter(SearchFilter::class, properties: ['league' => 'exact', 'user' => 'exact'])]
#[ORM\Entity]
#[ORM\Table(name: 'league_memberships')]
#[ORM\UniqueConstraint(name: 'uq_league_memberships', columns: ['league_id', 'user_id'])]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_league_memberships_league', columns: ['league_id'])]
#[ORM\Index(name: 'idx_league_memberships_user', columns: ['user_id'])]
class LeagueMembership
{
    use EntityIdTrait;

    #[ORM\ManyToOne(targetEntity: League::class, inversedBy: 'memberships')]
    #[ORM\JoinColumn(name: 'league_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['read', 'write'])]
    private ?League $league = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'leagueMemberships')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    #[Groups(['read', 'write'])]
    private ?User $user = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $nickname = null;

    #[ORM\Column(type: Types::STRING, options: ['default' => 'member'])]
    #[Groups(['read', 'write'])]
    private string $role = 'member';

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    #[Groups(['read'])]
    private \DateTimeImmutable $joinedAt;

    /** @var Collection<int, DraftPick> */
    #[ORM\OneToMany(targetEntity: DraftPick::class, mappedBy: 'leagueMembership')]
    private Collection $draftPicks;

    /** @var Collection<int, Lineup> */
    #[ORM\OneToMany(targetEntity: Lineup::class, mappedBy: 'leagueMembership', orphanRemoval: true)]
    private Collection $lineups;

    /** @var Collection<int, Score> */
    #[ORM\OneToMany(targetEntity: Score::class, mappedBy: 'leagueMembership', orphanRemoval: true)]
    private Collection $scores;

    public function __construct()
    {
        $this->joinedAt = new \DateTimeImmutable();
        $this->draftPicks = new ArrayCollection();
        $this->lineups = new ArrayCollection();
        $this->scores = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->nickname ?: ($this->user ? $this->user->getDisplayName() : 'Member');
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getNickname(): ?string
    {
        return $this->nickname;
    }

    public function setNickname(?string $nickname): static
    {
        $this->nickname = $nickname;

        return $this;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getJoinedAt(): \DateTimeImmutable
    {
        return $this->joinedAt;
    }

    /** @return Collection<int, DraftPick> */
    public function getDraftPicks(): Collection
    {
        return $this->draftPicks;
    }

    /** @return Collection<int, Lineup> */
    public function getLineups(): Collection
    {
        return $this->lineups;
    }

    /** @return Collection<int, Score> */
    public function getScores(): Collection
    {
        return $this->scores;
    }
}
