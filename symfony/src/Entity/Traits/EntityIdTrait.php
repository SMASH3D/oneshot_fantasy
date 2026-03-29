<?php

declare(strict_types=1);

namespace App\Entity\Traits;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * UUID primary key with v7 assignment before insert (PostgreSQL also defaults gen_random_uuid()).
 */
trait EntityIdTrait
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $id = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    #[ORM\PrePersist]
    public function assignEntityId(): void
    {
        $this->id ??= Uuid::v7();
    }
}
