<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\DraftSession;
use App\Exception\FantasyViolation;
use Doctrine\ORM\EntityManagerInterface;

/** POST /api/draft_sessions/{id}/start — transitions draft from pending to in_progress. */
final class DraftStartProcessor implements ProcessorInterface
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): DraftSession
    {
        if (!$data instanceof DraftSession) {
            throw new \LogicException('Expected DraftSession');
        }

        if (in_array($data->getStatus(), ['completed', 'cancelled'], true)) {
            throw new FantasyViolation('Draft cannot be started in its current state', 'conflict', 409);
        }

        $data->setStatus('in_progress');
        $data->touchUpdatedAt();

        $league = $data->getLeague();
        if ($league !== null) {
            $league->setStatus('draft');
            $league->touchUpdatedAt();
        }

        $this->em->flush();

        return $data;
    }
}
