<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\Traits\EntityIdTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
    ],
    normalizationContext: ['groups' => ['read']],
)]
#[ORM\Entity]
#[ORM\Table(name: 'scoring_config_presets')]
#[ORM\UniqueConstraint(name: 'uq_scoring_config_presets_hash', columns: ['scoring_config_hash'])]
#[UniqueEntity('scoringConfigHash')]
#[ORM\HasLifecycleCallbacks]
class ScoringConfigPreset
{
    use EntityIdTrait;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Groups(['read'])]
    private string $name = '';

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    #[Groups(['read'])]
    private array $scoringConfig = [];

    #[ORM\Column(type: Types::STRING, length: 64, nullable: false)]
    #[Groups(['read'])]
    private string $scoringConfigHash = '';

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
        return $this->name ?: 'Preset';
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

    /** @return array<string, mixed> */
    public function getScoringConfig(): array
    {
        return $this->scoringConfig;
    }

    /** @param array<string, mixed> $scoringConfig */
    public function setScoringConfig(array $scoringConfig): static
    {
        $this->scoringConfig = $scoringConfig;

        return $this;
    }

    public function getScoringConfigHash(): string
    {
        return $this->scoringConfigHash;
    }

    public function setScoringConfigHash(string $scoringConfigHash): static
    {
        $this->scoringConfigHash = $scoringConfigHash;

        return $this;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updateScoringConfigHash(): void
    {
        $config = $this->scoringConfig;
        $this->recursiveKsort($config);
        $json = json_encode($config, JSON_THROW_ON_ERROR);
        $this->scoringConfigHash = hash('sha256', $json);
    }

    private function recursiveKsort(array &$array): void
    {
        ksort($array);
        foreach ($array as &$value) {
            if (is_array($value)) {
                $this->recursiveKsort($value);
            }
        }
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
