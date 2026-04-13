<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\DraftPick;
use App\Entity\DraftSession;
use App\Entity\LeagueMembership;
use App\Exception\FantasyViolation;
use App\Service\Draft\DraftOrderResolver;
use Doctrine\ORM\EntityManagerInterface;

/**
 * POST /api/draft_picks — validates it's the membership's turn, assigns pickIndex, persists.
 *
 * The request body is deserialized into DraftPick (draftSession IRI + leagueMembership IRI + participant IRI).
 */
final class DraftPickCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DraftOrderResolver $orderResolver,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): DraftPick
    {
        if (!$data instanceof DraftPick) {
            throw new \LogicException('Expected DraftPick');
        }

        $session = $data->getDraftSession();
        if (!$session instanceof DraftSession) {
            throw new FantasyViolation('draftSession is required', 'validation_error', 422);
        }

        if ($session->getStatus() !== 'in_progress') {
            throw new FantasyViolation('Draft is not in progress', 'conflict', 409);
        }

        $membership = $data->getLeagueMembership();
        if (!$membership instanceof LeagueMembership) {
            throw new FantasyViolation('leagueMembership is required', 'validation_error', 422);
        }

        $league = $session->getLeague();
        if ($league === null || (string) $membership->getLeague()?->getId() !== (string) $league->getId()) {
            throw new FantasyViolation('leagueMembership does not belong to this league', 'validation_error', 422);
        }

        if ($data->getParticipant() === null) {
            throw new FantasyViolation('participant is required', 'validation_error', 422);
        }

        $participant = $data->getParticipant();
        $tournament = $league->getTournament();
        if ($tournament !== null && $participant->getSport() !== $tournament->getSportKey()) {
            throw new FantasyViolation('Participant sport does not match this league tournament', 'validation_error', 422);
        }

        // Duplicate pick check
        foreach ($session->getPicks() as $existing) {
            if ((string) $existing->getParticipant()?->getId() === (string) $participant->getId()) {
                throw new FantasyViolation('Participant already drafted in this session', 'conflict', 409);
            }
        }

        // Turn order validation
        $order = $this->resolveOrder($session);
        if ($order === []) {
            throw new FantasyViolation('No draft order configured and league has no members', 'validation_error', 422);
        }

        $nextIdx = $this->nextPickIndex($session);
        $snake = (bool) ($session->getConfig()['snake'] ?? false);
        $expectedId = $this->orderResolver->membershipIdAtPickIndex($order, $nextIdx, $snake);
        if ((string) $membership->getId() !== $expectedId) {
            throw new FantasyViolation('It is not this membership\'s turn to pick', 'conflict', 409);
        }

        $data->setPickIndex($nextIdx);
        $session->touchUpdatedAt();

        $this->em->persist($data);
        $this->em->flush();

        return $data;
    }

    /** @return list<string> */
    private function resolveOrder(DraftSession $session): array
    {
        $configured = $session->getConfig()['order_membership_ids'] ?? null;
        if (is_array($configured) && $configured !== []) {
            return array_values(array_filter($configured, static fn ($v) => is_string($v) && $v !== ''));
        }

        $league = $session->getLeague();
        if ($league === null) {
            return [];
        }

        $members = $league->getMemberships()->toArray();
        usort($members, static fn (LeagueMembership $a, LeagueMembership $b) => strcmp(
            $a->getNickname() ?? $a->getUser()?->getDisplayName() ?? '',
            $b->getNickname() ?? $b->getUser()?->getDisplayName() ?? '',
        ));

        return array_map(static fn (LeagueMembership $m) => (string) $m->getId(), $members);
    }

    private function nextPickIndex(DraftSession $session): int
    {
        $max = -1;
        foreach ($session->getPicks() as $p) {
            $max = max($max, $p->getPickIndex());
        }

        return $max + 1;
    }
}
