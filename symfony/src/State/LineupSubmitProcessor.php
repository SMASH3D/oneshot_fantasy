<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Lineup;
use App\Exception\FantasyViolation;
use Doctrine\ORM\EntityManagerInterface;

/** POST /api/lineup_submissions/{id}/submit — locks the lineup (status draft → locked). */
final class LineupSubmitProcessor implements ProcessorInterface
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Lineup
    {
        if (!$data instanceof Lineup) {
            throw new \LogicException('Expected Lineup');
        }

        if ($data->getStatus() === 'void') {
            throw new FantasyViolation('Lineup is void and cannot be submitted', 'conflict', 409);
        }

        if ($data->getStatus() === 'locked') {
            throw new FantasyViolation('Lineup is already locked', 'conflict', 409);
        }

        $data->setStatus('locked');
        $data->setSubmittedAt(new \DateTimeImmutable());
        $data->touchUpdatedAt();

        $this->em->flush();

        return $data;
    }
}
