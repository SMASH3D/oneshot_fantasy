<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\FantasyRound;
use App\Entity\League;
use App\Entity\Score;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route('/api/v1')]
final class ScoresController extends AbstractController
{
    use ApiJsonTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route(
        '/leagues/{leagueId}/rounds/{fantasyRoundId}/scores',
        name: 'api_v1_scores_round',
        methods: ['GET'],
        requirements: ['leagueId' => Requirement::UUID, 'fantasyRoundId' => Requirement::UUID],
    )]
    public function roundScores(string $leagueId, string $fantasyRoundId): JsonResponse
    {
        $league = $this->em->find(League::class, $leagueId);
        if (!$league instanceof League) {
            return $this->jsonDetail('League not found', 'not_found', Response::HTTP_NOT_FOUND);
        }

        $round = $this->em->find(FantasyRound::class, $fantasyRoundId);
        if (!$round instanceof FantasyRound || (string) $round->getLeague()?->getId() !== (string) $league->getId()) {
            return $this->jsonDetail('Fantasy round not found', 'not_found', Response::HTTP_NOT_FOUND);
        }

        $scores = $this->em->getRepository(Score::class)->findBy(['fantasyRound' => $round]);
        $rows = $this->buildRowsFromScores($scores, true);

        return $this->jsonData([
            'fantasy_round_id' => (string) $round->getId(),
            'rows' => $rows,
        ]);
    }

    #[Route('/leagues/{leagueId}/scores', name: 'api_v1_scores_cumulative', methods: ['GET'], requirements: ['leagueId' => Requirement::UUID])]
    public function cumulativeScores(string $leagueId): JsonResponse
    {
        $league = $this->em->find(League::class, $leagueId);
        if (!$league instanceof League) {
            return $this->jsonDetail('League not found', 'not_found', Response::HTTP_NOT_FOUND);
        }

        $rows = $this->cumulativeRows($league, false);

        return $this->jsonData([
            'league_id' => (string) $league->getId(),
            'rows' => $rows,
        ]);
    }

    #[Route('/leagues/{leagueId}/leaderboard', name: 'api_v1_leaderboard', methods: ['GET'], requirements: ['leagueId' => Requirement::UUID])]
    public function leaderboard(string $leagueId, Request $request): JsonResponse
    {
        $league = $this->em->find(League::class, $leagueId);
        if (!$league instanceof League) {
            return $this->jsonDetail('League not found', 'not_found', Response::HTTP_NOT_FOUND);
        }

        $roundId = $request->query->get('fantasy_round_id');
        if (\is_string($roundId) && $roundId !== '') {
            if (!Uuid::isValid($roundId)) {
                return $this->jsonDetail('Invalid fantasy_round_id', 'validation_error', Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $round = $this->em->find(FantasyRound::class, $roundId);
            if (!$round instanceof FantasyRound || (string) $round->getLeague()?->getId() !== (string) $league->getId()) {
                return $this->jsonDetail('Fantasy round not found', 'not_found', Response::HTTP_NOT_FOUND);
            }
            $scores = $this->em->getRepository(Score::class)->findBy(['fantasyRound' => $round]);
            $rows = $this->buildRowsFromScores($scores, true);

            return $this->jsonData([
                'league_id' => (string) $league->getId(),
                'fantasy_round_id' => (string) $round->getId(),
                'rows' => $rows,
            ]);
        }

        $rows = $this->cumulativeRows($league, true);

        return $this->jsonData([
            'league_id' => (string) $league->getId(),
            'rows' => $rows,
        ]);
    }

    #[Route(
        '/leagues/{leagueId}/rounds/{fantasyRoundId}/scores/recompute',
        name: 'api_v1_scores_recompute',
        methods: ['POST'],
        requirements: ['leagueId' => Requirement::UUID, 'fantasyRoundId' => Requirement::UUID],
    )]
    public function recompute(string $leagueId, string $fantasyRoundId): JsonResponse
    {
        $league = $this->em->find(League::class, $leagueId);
        if (!$league instanceof League) {
            return $this->jsonDetail('League not found', 'not_found', Response::HTTP_NOT_FOUND);
        }

        $round = $this->em->find(FantasyRound::class, $fantasyRoundId);
        if (!$round instanceof FantasyRound || (string) $round->getLeague()?->getId() !== (string) $league->getId()) {
            return $this->jsonDetail('Fantasy round not found', 'not_found', Response::HTTP_NOT_FOUND);
        }

        return $this->jsonData([
            'status' => 'accepted',
            'fantasy_round_id' => (string) $round->getId(),
        ], Response::HTTP_ACCEPTED);
    }

    /**
     * @param list<Score> $scores
     * @return list<array<string, mixed>>
     */
    private function buildRowsFromScores(array $scores, bool $includeBreakdown): array
    {
        $rows = [];
        foreach ($scores as $score) {
            $m = $score->getLeagueMembership();
            $row = [
                'league_membership_id' => (string) $m?->getId(),
                'nickname' => $m?->getNickname() ?? $m?->getUser()?->getDisplayName(),
                'points' => (float) $score->getPoints(),
            ];
            if ($includeBreakdown) {
                $row['breakdown'] = $score->getBreakdown();
            }
            $rows[] = $row;
        }

        usort($rows, static fn (array $a, array $b) => $b['points'] <=> $a['points']);

        $rank = 1;
        foreach ($rows as $i => $row) {
            if ($i > 0 && (float) $row['points'] < (float) $rows[$i - 1]['points']) {
                $rank = $i + 1;
            }
            $rows[$i]['rank'] = $rank;
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function cumulativeRows(League $league, bool $withRank): array
    {
        $qb = $this->em->getRepository(Score::class)->createQueryBuilder('s')
            ->join('s.fantasyRound', 'fr')
            ->where('fr.league = :league')
            ->setParameter('league', $league);

        /** @var list<Score> $scores */
        $scores = $qb->getQuery()->getResult();

        /** @var array<string, array{membership_id: string, nickname: ?string, points: float}> $acc */
        $acc = [];
        foreach ($scores as $score) {
            $m = $score->getLeagueMembership();
            if ($m === null) {
                continue;
            }
            $id = (string) $m->getId();
            if (!isset($acc[$id])) {
                $acc[$id] = [
                    'membership_id' => $id,
                    'nickname' => $m->getNickname() ?? $m->getUser()?->getDisplayName(),
                    'points' => 0.0,
                ];
            }
            $acc[$id]['points'] += (float) $score->getPoints();
        }

        $rows = [];
        foreach ($acc as $row) {
            $rows[] = [
                'league_membership_id' => $row['membership_id'],
                'nickname' => $row['nickname'],
                'points' => $row['points'],
            ];
        }

        usort($rows, static fn (array $a, array $b) => $b['points'] <=> $a['points']);

        if ($withRank) {
            $rank = 1;
            foreach ($rows as $i => $row) {
                if ($i > 0 && (float) $row['points'] < (float) $rows[$i - 1]['points']) {
                    $rank = $i + 1;
                }
                $rows[$i]['rank'] = $rank;
            }
        }

        return $rows;
    }
}
