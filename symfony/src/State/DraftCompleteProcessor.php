<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\DraftSession;
use App\Exception\FantasyViolation;
use Doctrine\ORM\EntityManagerInterface;

/** POST /api/draft_sessions/{id}/complete — marks draft as completed, league as active. */
final class DraftCompleteProcessor implements ProcessorInterface
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): DraftSession
    {
        if (!$data instanceof DraftSession) {
            throw new \LogicException('Expected DraftSession');
        }

        if ($data->getStatus() === 'completed') {
            throw new FantasyViolation('Draft is already completed', 'conflict', 409);
        }

        $data->setStatus('completed');
        $data->touchUpdatedAt();

        $league = $data->getLeague();
        if ($league !== null) {
            $league->setStatus('active');
            $league->touchUpdatedAt();
        }

        $this->em->flush();

        return $data;
    }
}
