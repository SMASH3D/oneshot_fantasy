<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\FantasyRound;
use App\Entity\League;
use App\Entity\LeagueMembership;
use App\Entity\Lineup;
use App\Entity\Participant;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/api/v1')]
final class LineupController extends AbstractController
{
    use ApiJsonTrait;

    private const META_SLOTS_KEY = 'slots';

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route(
        '/leagues/{leagueId}/rounds/{fantasyRoundId}/lineups/{membershipId}',
        name: 'api_v1_lineup_get',
        methods: ['GET'],
        requirements: ['leagueId' => Requirement::UUID, 'fantasyRoundId' => Requirement::UUID, 'membershipId' => Requirement::UUID],
    )]
    public function get(
        string $leagueId,
        string $fantasyRoundId,
        string $membershipId,
    ): JsonResponse {
        $ctx = $this->resolveContext($leagueId, $fantasyRoundId, $membershipId);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        [$league, $round, $membership] = $ctx;

        $lineup = $this->findOrCreateLineup($round, $membership, $league);
        $this->em->flush();

        return $this->jsonData($this->lineupPayload($lineup, $league));
    }

    #[Route(
        '/leagues/{leagueId}/rounds/{fantasyRoundId}/lineups/{membershipId}',
        name: 'api_v1_lineup_put',
        methods: ['PUT'],
        requirements: ['leagueId' => Requirement::UUID, 'fantasyRoundId' => Requirement::UUID, 'membershipId' => Requirement::UUID],
    )]
    public function put(
        string $leagueId,
        string $fantasyRoundId,
        string $membershipId,
        Request $request,
    ): JsonResponse {
        $ctx = $this->resolveContext($leagueId, $fantasyRoundId, $membershipId);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        [$league, $round, $membership] = $ctx;

        try {
            $body = $this->parseJsonBody($request);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonDetail($e->getMessage(), 'invalid_json', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!isset($body['slots']) || !\is_array($body['slots'])) {
            return $this->jsonDetail('slots array is required', 'validation_error', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $lineup = $this->findOrCreateLineup($round, $membership, $league);
        if (\in_array($lineup->getStatus(), ['locked', 'void'], true)) {
            return $this->jsonDetail('Lineup cannot be edited in this state', 'conflict', Response::HTTP_CONFLICT);
        }

        $normalized = $this->normalizeIncomingSlots($body['slots'], $league);
        if ($normalized instanceof JsonResponse) {
            return $normalized;
        }

        $meta = $lineup->getMetadata();
        $meta[self::META_SLOTS_KEY] = $normalized;
        $lineup->setMetadata($meta);
        $lineup->touchUpdatedAt();
        $this->em->flush();

        return $this->jsonData($this->lineupPayload($lineup, $league));
    }

    #[Route(
        '/leagues/{leagueId}/rounds/{fantasyRoundId}/lineups/{membershipId}/submit',
        name: 'api_v1_lineup_submit',
        methods: ['POST'],
        requirements: ['leagueId' => Requirement::UUID, 'fantasyRoundId' => Requirement::UUID, 'membershipId' => Requirement::UUID],
    )]
    public function submit(
        string $leagueId,
        string $fantasyRoundId,
        string $membershipId,
    ): JsonResponse {
        $ctx = $this->resolveContext($leagueId, $fantasyRoundId, $membershipId);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        [$league, $round, $membership] = $ctx;

        $lineup = $this->findOrCreateLineup($round, $membership, $league);
        if ($lineup->getStatus() === 'void') {
            return $this->jsonDetail('Lineup is void', 'conflict', Response::HTTP_CONFLICT);
        }

        $now = new \DateTimeImmutable();
        $lineup->setStatus('locked');
        $lineup->setSubmittedAt($now);
        $lineup->touchUpdatedAt();
        $this->em->flush();

        return $this->jsonData([
            'status' => 'locked',
            'submitted_at' => $this->formatInstant($now),
        ]);
    }

    /**
     * @return JsonResponse|array{League, FantasyRound, LeagueMembership}
     */
    private function resolveContext(string $leagueId, string $fantasyRoundId, string $membershipId): JsonResponse|array
    {
        $league = $this->em->find(League::class, $leagueId);
        if (!$league instanceof League) {
            return $this->jsonDetail('League not found', 'not_found', Response::HTTP_NOT_FOUND);
        }

        $round = $this->em->find(FantasyRound::class, $fantasyRoundId);
        if (!$round instanceof FantasyRound || (string) $round->getLeague()?->getId() !== (string) $league->getId()) {
            return $this->jsonDetail('Fantasy round not found', 'not_found', Response::HTTP_NOT_FOUND);
        }

        $membership = $this->em->find(LeagueMembership::class, $membershipId);
        if (!$membership instanceof LeagueMembership || (string) $membership->getLeague()?->getId() !== (string) $league->getId()) {
            return $this->jsonDetail('Membership not found', 'not_found', Response::HTTP_NOT_FOUND);
        }

        return [$league, $round, $membership];
    }

    private function findOrCreateLineup(FantasyRound $round, LeagueMembership $membership, League $league): Lineup
    {
        $lineup = $this->em->getRepository(Lineup::class)->findOneBy([
            'fantasyRound' => $round,
            'leagueMembership' => $membership,
        ]);

        if ($lineup instanceof Lineup) {
            return $lineup;
        }

        $lineup = new Lineup();
        $lineup->setFantasyRound($round);
        $lineup->setLeagueMembership($membership);
        $lineup->setStatus('draft');
        $meta = $lineup->getMetadata();
        $meta[self::META_SLOTS_KEY] = $this->defaultSlotsFromLeague($league);
        $lineup->setMetadata($meta);
        $this->em->persist($lineup);

        return $lineup;
    }

    /**
     * @return list<array{order_index: int, slot_role: string, participant_id: string|null}>
     */
    private function defaultSlotsFromLeague(League $league): array
    {
        $tpl = $league->getLineupTemplate();
        if ($tpl === []) {
            return [['order_index' => 0, 'slot_role' => 'starter', 'participant_id' => null]];
        }

        $slots = [];
        foreach ($tpl as $i => $row) {
            $role = \is_array($row) && isset($row['role']) && \is_string($row['role']) ? $row['role'] : 'slot_'.$i;
            $slots[] = ['order_index' => $i, 'slot_role' => $role, 'participant_id' => null];
        }

        return $slots;
    }

    /**
     * @param list<mixed> $raw
     * @return JsonResponse|list<array{order_index: int, slot_role: string, participant_id: string|null}>
     */
    private function normalizeIncomingSlots(array $raw, League $league): JsonResponse|array
    {
        $tournament = $league->getTournament();
        $out = [];
        foreach ($raw as $i => $row) {
            if (!\is_array($row)) {
                return $this->jsonDetail(\sprintf('Invalid slot at index %d', $i), 'validation_error', Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $order = $row['order_index'] ?? null;
            $role = $row['slot_role'] ?? null;
            if (!\is_int($order) && !(\is_string($order) && ctype_digit($order))) {
                return $this->jsonDetail('Each slot requires order_index', 'validation_error', Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $orderIndex = (int) $order;
            if (!\is_string($role) || $role === '') {
                return $this->jsonDetail('Each slot requires slot_role', 'validation_error', Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $pid = null;
            if (\array_key_exists('participant_id', $row) && $row['participant_id'] !== null) {
                if (!\is_string($row['participant_id'])) {
                    return $this->jsonDetail('participant_id must be a string or null', 'validation_error', Response::HTTP_UNPROCESSABLE_ENTITY);
                }
                $participant = $this->em->find(Participant::class, $row['participant_id']);
                if (!$participant instanceof Participant) {
                    return $this->jsonDetail('Unknown participant_id in slots', 'not_found', Response::HTTP_UNPROCESSABLE_ENTITY);
                }
                if ($tournament === null || (string) $participant->getTournament()?->getId() !== (string) $tournament->getId()) {
                    return $this->jsonDetail('Participant does not belong to this tournament', 'validation_error', Response::HTTP_UNPROCESSABLE_ENTITY);
                }
                $pid = $row['participant_id'];
            }
            $out[] = ['order_index' => $orderIndex, 'slot_role' => $role, 'participant_id' => $pid];
        }

        usort($out, static fn (array $a, array $b) => $a['order_index'] <=> $b['order_index']);

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function lineupPayload(Lineup $lineup, League $league): array
    {
        $meta = $lineup->getMetadata();
        $slots = $meta[self::META_SLOTS_KEY] ?? null;
        if (!\is_array($slots) || $slots === []) {
            $slots = $this->defaultSlotsFromLeague($league);
        }

        $normalized = [];
        foreach ($slots as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $normalized[] = [
                'order_index' => (int) ($row['order_index'] ?? 0),
                'slot_role' => (string) ($row['slot_role'] ?? ''),
                'participant_id' => isset($row['participant_id']) && \is_string($row['participant_id']) ? $row['participant_id'] : null,
            ];
        }

        return [
            'fantasy_round_id' => (string) $lineup->getFantasyRound()?->getId(),
            'league_membership_id' => (string) $lineup->getLeagueMembership()?->getId(),
            'status' => $lineup->getStatus(),
            'slots' => $normalized,
        ];
    }

    private function formatInstant(?\DateTimeImmutable $dt): ?string
    {
        if ($dt === null) {
            return null;
        }

        return $dt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }
}
