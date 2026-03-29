<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\DraftPick;
use App\Entity\DraftSession;
use App\Entity\League;
use App\Entity\LeagueMembership;
use App\Entity\Participant;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route('/api/v1')]
final class DraftController extends AbstractController
{
    use ApiJsonTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/leagues/{leagueId}/draft/configure', name: 'api_v1_draft_configure', methods: ['POST'], requirements: ['leagueId' => Requirement::UUID])]
    public function configure(string $leagueId, Request $request): JsonResponse
    {
        $league = $this->em->find(League::class, $leagueId);
        if (!$league instanceof League) {
            return $this->jsonDetail('League not found', 'not_found', Response::HTTP_NOT_FOUND);
        }

        try {
            $body = $this->parseJsonBody($request);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonDetail($e->getMessage(), 'invalid_json', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $session = $league->getDraftSession();
        if (!$session instanceof DraftSession) {
            $session = new DraftSession();
            $league->setDraftSession($session);
            $this->em->persist($session);
        }

        $config = $session->getConfig();
        if (isset($body['snake'])) {
            $config['snake'] = (bool) $body['snake'];
        }
        if (isset($body['pick_time_seconds']) && (is_int($body['pick_time_seconds']) || is_float($body['pick_time_seconds']))) {
            $config['pick_time_seconds'] = (int) $body['pick_time_seconds'];
        }
        if (isset($body['order_membership_ids']) && \is_array($body['order_membership_ids'])) {
            $ids = [];
            foreach ($body['order_membership_ids'] as $id) {
                if (\is_string($id) && Uuid::isValid($id)) {
                    $ids[] = $id;
                }
            }
            $err = $this->validateMembershipsBelongToLeague($league, $ids);
            if ($err !== null) {
                return $this->jsonDetail($err, 'validation_error', Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $config['order_membership_ids'] = $ids;
        }
        $session->setConfig($config);
        $session->touchUpdatedAt();
        $this->em->flush();

        return $this->jsonData($this->configurePayload($league, $session));
    }

    #[Route('/leagues/{leagueId}/draft/start', name: 'api_v1_draft_start', methods: ['POST'], requirements: ['leagueId' => Requirement::UUID])]
    public function start(string $leagueId): JsonResponse
    {
        $league = $this->em->find(League::class, $leagueId);
        if (!$league instanceof League) {
            return $this->jsonDetail('League not found', 'not_found', Response::HTTP_NOT_FOUND);
        }

        $session = $league->getDraftSession();
        if (!$session instanceof DraftSession) {
            return $this->jsonDetail('Draft session not configured', 'validation_error', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($session->getStatus() === 'completed' || $session->getStatus() === 'cancelled') {
            return $this->jsonDetail('Draft cannot be started in this state', 'conflict', Response::HTTP_CONFLICT);
        }

        $session->setStatus('in_progress');
        $session->touchUpdatedAt();
        $league->setStatus('draft');
        $league->touchUpdatedAt();

        $deadline = null;
        $pickSeconds = $session->getConfig()['pick_time_seconds'] ?? null;
        if (\is_int($pickSeconds) && $pickSeconds > 0) {
            $deadline = (new \DateTimeImmutable())->add(new \DateInterval('PT'.$pickSeconds.'S'));
        }

        $this->em->flush();

        return $this->jsonData([
            'draft_session_id' => (string) $session->getId(),
            'status' => $session->getStatus(),
            'current_pick_index' => $this->nextPickIndex($session),
            'deadline_at' => $this->formatInstant($deadline),
        ]);
    }

    #[Route('/leagues/{leagueId}/draft', name: 'api_v1_draft_get', methods: ['GET'], requirements: ['leagueId' => Requirement::UUID])]
    public function getState(string $leagueId): JsonResponse
    {
        $league = $this->em->find(League::class, $leagueId);
        if (!$league instanceof League) {
            return $this->jsonDetail('League not found', 'not_found', Response::HTTP_NOT_FOUND);
        }

        $session = $league->getDraftSession();
        if (!$session instanceof DraftSession) {
            return $this->jsonDetail('Draft session not found', 'not_found', Response::HTTP_NOT_FOUND);
        }

        $picks = $session->getPicks()->toArray();
        usort($picks, static fn (DraftPick $a, DraftPick $b) => $a->getPickIndex() <=> $b->getPickIndex());

        return $this->jsonData([
            'draft_session_id' => (string) $session->getId(),
            'status' => $session->getStatus(),
            'current_pick_index' => $this->nextPickIndex($session),
            'picks' => array_map(static fn (DraftPick $p) => [
                'pick_index' => $p->getPickIndex(),
                'league_membership_id' => (string) $p->getLeagueMembership()?->getId(),
                'participant_id' => (string) $p->getParticipant()?->getId(),
            ], $picks),
        ]);
    }

    #[Route('/leagues/{leagueId}/draft/picks', name: 'api_v1_draft_pick', methods: ['POST'], requirements: ['leagueId' => Requirement::UUID])]
    public function pick(string $leagueId, Request $request): JsonResponse
    {
        $league = $this->em->find(League::class, $leagueId);
        if (!$league instanceof League) {
            return $this->jsonDetail('League not found', 'not_found', Response::HTTP_NOT_FOUND);
        }

        $session = $league->getDraftSession();
        if (!$session instanceof DraftSession) {
            return $this->jsonDetail('Draft session not found', 'not_found', Response::HTTP_NOT_FOUND);
        }

        if ($session->getStatus() !== 'in_progress') {
            return $this->jsonDetail('Draft is not in progress', 'conflict', Response::HTTP_CONFLICT);
        }

        try {
            $body = $this->parseJsonBody($request);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonDetail($e->getMessage(), 'invalid_json', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $mid = isset($body['league_membership_id']) && \is_string($body['league_membership_id']) ? $body['league_membership_id'] : '';
        $pid = isset($body['participant_id']) && \is_string($body['participant_id']) ? $body['participant_id'] : '';
        if ($mid === '' || $pid === '') {
            return $this->jsonDetail('league_membership_id and participant_id are required', 'validation_error', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $membership = $this->em->find(LeagueMembership::class, $mid);
        if (!$membership instanceof LeagueMembership || (string) $membership->getLeague()?->getId() !== (string) $league->getId()) {
            return $this->jsonDetail('league_membership_id is not valid for this league', 'validation_error', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $participant = $this->em->find(Participant::class, $pid);
        if (!$participant instanceof Participant) {
            return $this->jsonDetail('participant_id not found', 'not_found', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $tournament = $league->getTournament();
        if ($tournament === null || (string) $participant->getTournament()?->getId() !== (string) $tournament->getId()) {
            return $this->jsonDetail('Participant does not belong to this league tournament', 'validation_error', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        foreach ($session->getPicks() as $existing) {
            if ((string) $existing->getParticipant()?->getId() === (string) $participant->getId()) {
                return $this->jsonDetail('Participant already drafted in this session', 'conflict', Response::HTTP_CONFLICT);
            }
        }

        $pickIndex = $this->nextPickIndex($session);
        $pick = new DraftPick();
        $pick->setDraftSession($session);
        $pick->setPickIndex($pickIndex);
        $pick->setLeagueMembership($membership);
        $pick->setParticipant($participant);
        $this->em->persist($pick);
        $session->touchUpdatedAt();
        $this->em->flush();

        return $this->jsonData([
            'pick_index' => $pickIndex,
            'league_membership_id' => (string) $membership->getId(),
            'participant_id' => (string) $participant->getId(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/leagues/{leagueId}/draft/complete', name: 'api_v1_draft_complete', methods: ['POST'], requirements: ['leagueId' => Requirement::UUID])]
    public function complete(string $leagueId): JsonResponse
    {
        $league = $this->em->find(League::class, $leagueId);
        if (!$league instanceof League) {
            return $this->jsonDetail('League not found', 'not_found', Response::HTTP_NOT_FOUND);
        }

        $session = $league->getDraftSession();
        if (!$session instanceof DraftSession) {
            return $this->jsonDetail('Draft session not found', 'not_found', Response::HTTP_NOT_FOUND);
        }

        $session->setStatus('completed');
        $session->touchUpdatedAt();
        $league->setStatus('active');
        $league->touchUpdatedAt();
        $this->em->flush();

        return $this->jsonData([
            'draft_session_id' => (string) $session->getId(),
            'status' => $session->getStatus(),
            'league_status' => $league->getStatus(),
        ]);
    }

    /**
     * @param list<string> $membershipIds
     */
    private function validateMembershipsBelongToLeague(League $league, array $membershipIds): ?string
    {
        foreach ($membershipIds as $id) {
            $m = $this->em->find(LeagueMembership::class, $id);
            if (!$m instanceof LeagueMembership || (string) $m->getLeague()?->getId() !== (string) $league->getId()) {
                return 'order_membership_ids must reference members of this league';
            }
        }

        return null;
    }

    private function nextPickIndex(DraftSession $session): int
    {
        $max = -1;
        foreach ($session->getPicks() as $p) {
            $max = max($max, $p->getPickIndex());
        }

        return $max + 1;
    }

    /**
     * @return array<string, mixed>
     */
    private function configurePayload(League $league, DraftSession $session): array
    {
        return [
            'league_id' => (string) $league->getId(),
            'draft_session_id' => (string) $session->getId(),
            'status' => $session->getStatus(),
            'config' => $session->getConfig(),
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
