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
    order: ['name' => 'ASC'],
)]
#[ApiFilter(SearchFilter::class, properties: ['sport' => 'exact', 'externalId' => 'exact'])]
#[ORM\Entity]
#[ORM\Table(name: 'teams')]
#[ORM\UniqueConstraint(name: 'uq_teams_external_id_sport', columns: ['external_id', 'sport'])]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_teams_external_id', columns: ['external_id'])]
#[ORM\Index(name: 'idx_teams_sport', columns: ['sport'])]
class Team
{
    use EntityIdTrait;

    #[ORM\Column(name: 'external_id', type: Types::STRING, length: 255)]
    #[Groups(['read'])]
    private string $externalId = '';

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Groups(['read'])]
    private string $name = '';

    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    #[Groups(['read'])]
    private ?string $abbreviation = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['read'])]
    private ?string $city = null;

    #[ORM\Column(type: Types::STRING, length: 64)]
    #[Groups(['read'])]
    private string $sport = '';

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['read'])]
    private ?array $metadata = null;

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
        return $this->city ? $this->city . ' ' . $this->name : ($this->name ?: 'Team');
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    public function setExternalId(string $externalId): static
    {
        $this->externalId = $externalId;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getAbbreviation(): ?string
    {
        return $this->abbreviation;
    }

    public function setAbbreviation(?string $abbreviation): static
    {
        $this->abbreviation = $abbreviation;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getSport(): string
    {
        return $this->sport;
    }

    public function setSport(string $sport): static
    {
        $this->sport = $sport;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    /** @param array<string, mixed>|null $metadata */
    public function setMetadata(?array $metadata): static
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

    #[ORM\PreUpdate]
    public function touchUpdatedAt(): static
    {
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }
}
