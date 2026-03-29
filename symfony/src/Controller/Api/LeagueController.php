<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\FantasyRound;
use App\Entity\League;
use App\Entity\LeagueMembership;
use App\Entity\Tournament;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route('/api/v1')]
final class LeagueController extends AbstractController
{
    use ApiJsonTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/leagues', name: 'api_v1_leagues_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $qb = $this->em->getRepository(League::class)->createQueryBuilder('l')->orderBy('l.name', 'ASC');
        $tid = $request->query->get('tournament_id');
        if (\is_string($tid) && $tid !== '') {
            if (!Uuid::isValid($tid)) {
                return $this->jsonDetail('Invalid tournament_id', 'validation_error', Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $qb->andWhere('l.tournament = :t')->setParameter('t', $tid);
        }

        /** @var list<League> $rows */
        $rows = $qb->getQuery()->getResult();

        return $this->jsonData(array_map(fn (League $l) => $this->leagueListItem($l), $rows));
    }

    #[Route('/tournaments/{tournamentId}/leagues', name: 'api_v1_tournaments_leagues_list', methods: ['GET'], requirements: ['tournamentId' => Requirement::UUID])]
    public function listForTournament(string $tournamentId): JsonResponse
    {
        $tournament = $this->em->find(Tournament::class, $tournamentId);
        if (!$tournament instanceof Tournament) {
            return $this->jsonDetail('Tournament not found', 'not_found', Response::HTTP_NOT_FOUND);
        }

        $rows = $tournament->getLeagues()->toArray();
        usort($rows, static fn (League $a, League $b) => strcmp($a->getName(), $b->getName()));

        return $this->jsonData(array_map(fn (League $l) => $this->leagueListItem($l), $rows));
    }

    #[Route('/tournaments/{tournamentId}/leagues', name: 'api_v1_tournaments_leagues_create', methods: ['POST'], requirements: ['tournamentId' => Requirement::UUID])]
    public function createForTournament(string $tournamentId, Request $request): JsonResponse
    {
        $tournament = $this->em->find(Tournament::class, $tournamentId);
        if (!$tournament instanceof Tournament) {
            return $this->jsonDetail('Tournament not found', 'not_found', Response::HTTP_NOT_FOUND);
        }

        try {
            $body = $this->parseJsonBody($request);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonDetail($e->getMessage(), 'invalid_json', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $name = isset($body['name']) && \is_string($body['name']) ? trim($body['name']) : '';
        $commissionerId = isset($body['commissioner_user_id']) && \is_string($body['commissioner_user_id'])
            ? $body['commissioner_user_id']
            : '';
        if ($name === '' || $commissionerId === '') {
            return $this->jsonDetail('name and commissioner_user_id are required', 'validation_error', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = $this->em->find(User::class, $commissionerId);
        if (!$user instanceof User) {
            return $this->jsonDetail('commissioner_user_id not found', 'not_found', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $league = new League();
        $league->setTournament($tournament);
        $league->setName($name);
        $league->setCommissioner($user);
        $league->setStatus('forming');
        if (isset($body['settings']) && \is_array($body['settings'])) {
            /** @var array<string, mixed> $settings */
            $settings = $body['settings'];
            $league->setSettings($settings);
        }
        if (isset($body['lineup_template']) && \is_array($body['lineup_template'])) {
            /** @var list<array<string, mixed>> $tpl */
            $tpl = $body['lineup_template'];
            $league->setLineupTemplate($tpl);
        }

        $this->em->persist($league);
        $this->em->flush();

        return $this->jsonData($this->leagueDetail($league), Response::HTTP_CREATED);
    }

    #[Route('/leagues/{leagueId}', name: 'api_v1_leagues_get', methods: ['GET'], requirements: ['leagueId' => Requirement::UUID])]
    public function getOne(string $leagueId): JsonResponse
    {
        $league = $this->em->find(League::class, $leagueId);
        if (!$league instanceof League) {
            return $this->jsonDetail('League not found', 'not_found', Response::HTTP_NOT_FOUND);
        }

        return $this->jsonData($this->leagueDetail($league));
    }

    #[Route('/leagues/{leagueId}/members', name: 'api_v1_leagues_members_create', methods: ['POST'], requirements: ['leagueId' => Requirement::UUID])]
    public function addMember(string $leagueId, Request $request): JsonResponse
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

        $userId = isset($body['user_id']) && \is_string($body['user_id']) ? $body['user_id'] : '';
        if ($userId === '') {
            return $this->jsonDetail('user_id is required', 'validation_error', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = $this->em->find(User::class, $userId);
        if (!$user instanceof User) {
            return $this->jsonDetail('user_id not found', 'not_found', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $existing = $this->em->getRepository(LeagueMembership::class)->findOneBy([
            'league' => $league,
            'user' => $user,
        ]);
        if ($existing instanceof LeagueMembership) {
            return $this->jsonDetail('User is already a member of this league', 'conflict', Response::HTTP_CONFLICT);
        }

        $membership = new LeagueMembership();
        $membership->setLeague($league);
        $membership->setUser($user);
        $membership->setRole('member');
        if (isset($body['nickname']) && \is_string($body['nickname'])) {
            $membership->setNickname($body['nickname']);
        }

        $this->em->persist($membership);
        $this->em->flush();

        return $this->jsonData($this->membershipPayload($membership), Response::HTTP_CREATED);
    }

    #[Route('/leagues/{leagueId}/members', name: 'api_v1_leagues_members_list', methods: ['GET'], requirements: ['leagueId' => Requirement::UUID])]
    public function listMembers(string $leagueId): JsonResponse
    {
        $league = $this->em->find(League::class, $leagueId);
        if (!$league instanceof League) {
            return $this->jsonDetail('League not found', 'not_found', Response::HTTP_NOT_FOUND);
        }

        $members = $league->getMemberships()->toArray();
        usort($members, static fn (LeagueMembership $a, LeagueMembership $b) => strcmp(
            $a->getNickname() ?? $a->getUser()?->getDisplayName() ?? '',
            $b->getNickname() ?? $b->getUser()?->getDisplayName() ?? '',
        ));

        return $this->jsonData(array_map(fn (LeagueMembership $m) => $this->membershipPayload($m), $members));
    }

    #[Route('/leagues/{leagueId}/rounds', name: 'api_v1_leagues_rounds', methods: ['GET'], requirements: ['leagueId' => Requirement::UUID])]
    public function fantasyRounds(string $leagueId): JsonResponse
    {
        $league = $this->em->find(League::class, $leagueId);
        if (!$league instanceof League) {
            return $this->jsonDetail('League not found', 'not_found', Response::HTTP_NOT_FOUND);
        }

        $rounds = $league->getFantasyRounds()->toArray();
        usort($rounds, static fn (FantasyRound $a, FantasyRound $b) => $a->getOrderIndex() <=> $b->getOrderIndex());

        return $this->jsonData(array_map(fn (FantasyRound $fr) => $this->fantasyRoundPayload($fr), $rounds));
    }

    /**
     * @return array<string, mixed>
     */
    private function leagueListItem(League $l): array
    {
        return [
            'id' => (string) $l->getId(),
            'tournament_id' => (string) $l->getTournament()?->getId(),
            'name' => $l->getName(),
            'status' => $l->getStatus(),
            'member_count' => $l->getMemberships()->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function leagueDetail(League $l): array
    {
        return [
            'id' => (string) $l->getId(),
            'tournament_id' => (string) $l->getTournament()?->getId(),
            'name' => $l->getName(),
            'commissioner_user_id' => (string) $l->getCommissioner()?->getId(),
            'status' => $l->getStatus(),
            'settings' => $l->getSettings(),
            'lineup_template' => $l->getLineupTemplate(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function membershipPayload(LeagueMembership $m): array
    {
        return [
            'id' => (string) $m->getId(),
            'league_id' => (string) $m->getLeague()?->getId(),
            'user_id' => (string) $m->getUser()?->getId(),
            'nickname' => $m->getNickname(),
            'role' => $m->getRole(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fantasyRoundPayload(FantasyRound $fr): array
    {
        return [
            'id' => (string) $fr->getId(),
            'league_id' => (string) $fr->getLeague()?->getId(),
            'tournament_round_id' => $this->uuidString($fr->getTournamentRound()?->getId()),
            'order_index' => $fr->getOrderIndex(),
            'name' => $fr->getName(),
            'opens_at' => $this->formatInstant($fr->getOpensAt()),
            'locks_at' => $this->formatInstant($fr->getLocksAt()),
            'metadata' => $fr->getMetadata(),
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
