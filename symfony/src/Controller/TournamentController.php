<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Game;
use App\Entity\League;
use App\Entity\Round;
use App\Entity\Tournament;
use App\Enum\BracketType;
use Doctrine\ORM\EntityManagerInterface;
use Kibatic\DatagridBundle\Grid\GridBuilder;
use Kibatic\DatagridBundle\Grid\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller for managing tournament-related pages and grids.
 *
 * Provides routes and functionality for listing tournaments, viewing individual
 * tournament details, displaying associated leagues, and managing NBA postseason brackets.
 */
#[Route('/tournaments')]
class TournamentController extends AbstractController
{
    #[Route('/', name: 'app_tournament')]
    public function index(
        Request $request,
        GridBuilder $gridBuilder,
        EntityManagerInterface $entityManager
    ): Response {
        $queryBuilder = $entityManager->getRepository(Tournament::class)
            ->createQueryBuilder('t')
            ->orderBy('t.startsAt', 'DESC');

        $grid = $gridBuilder->initialize($queryBuilder)
            ->addColumn('Sport', 'sportKey', sortable: 't.sportKey')
            ->addColumn('Name', 'name', sortable: 't.name')
            ->addColumn('Starts At', fn(Tournament $t) => $t->getStartsAt()?->format('Y-m-d H:i') ?? 'N/A', sortable: 't.startsAt')
            ->addColumn('Status', 'status', template: 'tournament/_status_badge.html.twig', sortable: 't.status')
            ->addColumn('Leagues', fn(Tournament $t) => $t->getLeagues()->count())
            ->addColumn('Actions', fn(Tournament $t) => [
                [
                    'name' => 'View',
                    'url' => $this->generateUrl('app_tournament_show', ['slug' => $t->getSlug()]),
                    'btn_type' => 'ui-btn ui-btn--ghost ui-btn--sm'
                ]
            ], template: Template::ACTIONS)
            ->getGrid();

        return $this->render('tournament/index.html.twig', [
            'grid' => $grid,
        ]);
    }

    #[Route('/{slug}', name: 'app_tournament_show')]
    public function show(
        Tournament $tournament,
        Request $request,
        GridBuilder $gridBuilder,
        EntityManagerInterface $entityManager
    ): Response {
        // --- Leagues grid ---
        $leaguesQuery = $entityManager->getRepository(League::class)
            ->createQueryBuilder('l')
            ->select('l', 'count(m.id) as HIDDEN membershipCount')
            ->leftJoin('l.memberships', 'm')
            ->where('l.tournament = :tournament')
            ->setParameter('tournament', $tournament)
            ->groupBy('l.id')
            ->orderBy('membershipCount', 'DESC');

        $leaguesGrid = $gridBuilder->initialize($leaguesQuery)
            ->addColumn('Name', 'name', sortable: 'l.name')
            ->addColumn('Players', fn(League $l) => $l->getMemberships()->count())
            ->addColumn('Status', 'status', template: 'league/_status_badge.html.twig', sortable: 'l.status')
            ->addColumn('Actions', fn(League $l) => [
                [
                    'name' => 'View',
                    'url' => $this->generateUrl('app_league_view', ['id' => $l->getId()]),
                    'btn_type' => 'ui-btn ui-btn--ghost ui-btn--sm'
                ]
            ], template: Template::ACTIONS)
            ->getGrid();

        // --- Bracket data (for NBA postseason bracket display) ---
        $bracketRounds = [];
        if ($tournament->getBracketType() === BracketType::NbaPostseason) {
            $bracketRounds = $this->buildNbaPostseasonBracket($tournament, $entityManager);
        }

        // Mock leaderboard data
        $mockLeaderboard = [
            ['rank' => 1, 'score' => 2450.5, 'player' => 'LeBron J.', 'league' => 'Champions League', 'leagueId' => 1],
            ['rank' => 2, 'score' => 2390.2, 'player' => 'Kevin D.', 'league' => 'East Coast Battle', 'leagueId' => 2],
            ['rank' => 3, 'score' => 2210.0, 'player' => 'Stephen C.', 'league' => 'Golden Era', 'leagueId' => 3],
            ['rank' => 4, 'score' => 2150.8, 'player' => 'Giannis A.', 'league' => 'Champions League', 'leagueId' => 1],
        ];

        return $this->render('tournament/show.html.twig', [
            'tournament' => $tournament,
            'leaguesGrid' => $leaguesGrid,
            'leaderboard' => $mockLeaderboard,
            'bracketRounds' => $bracketRounds,
        ]);
    }

    /**
     * Build the bracket data structure for NBA Postseason.
     *
     * Returns an ordered array of rounds, each containing its matchup series
     * and the individual games within each series.
     *
     * @return list<array{
     *   round: Round,
     *   games: list<Game>,
     *   winsHome: int,
     *   winsAway: int,
     *   seriesScore: string,
     * }>
     */
    private function buildNbaPostseasonBracket(
        Tournament $tournament,
        EntityManagerInterface $entityManager
    ): array {
        /** @var list<Round> $rounds */
        $rounds = $entityManager->getRepository(Round::class)
            ->createQueryBuilder('r')
            ->select('r', 'ht', 'at')
            ->leftJoin('r.homeTeam', 'ht')
            ->leftJoin('r.awayTeam', 'at')
            ->where('r.tournament = :tournament')
            ->setParameter('tournament', $tournament)
            ->orderBy('r.orderIndex', 'ASC')
            ->getQuery()
            ->getResult();

        if (empty($rounds)) {
            return [];
        }

        // Eager-load all games for this tournament, grouped by round
        /** @var list<Game> $allGames */
        $allGames = $entityManager->getRepository(Game::class)
            ->createQueryBuilder('g')
            ->where('g.tournament = :tournament')
            ->andWhere('g.tournamentRound IS NOT NULL')
            ->setParameter('tournament', $tournament)
            ->orderBy('g.date', 'ASC')
            ->getQuery()
            ->getResult();

        $gamesByRound = [];
        foreach ($allGames as $game) {
            $roundId = (string) $game->getTournamentRound()?->getId();
            if ($roundId) {
                $gamesByRound[$roundId][] = $game;
            }
        }

        $result = [];
        foreach ($rounds as $round) {
            $roundId = (string) $round->getId();
            $meta = $round->getMetadata();
            $winsHome = (int) ($meta['wins_home'] ?? 0);
            $winsAway = (int) ($meta['wins_away'] ?? 0);

            $result[] = [
                'round' => $round,
                'games' => $gamesByRound[$roundId] ?? [],
                'winsHome' => $winsHome,
                'winsAway' => $winsAway,
                'seriesScore' => $winsHome . '–' . $winsAway,
            ];
        }

        return $result;
    }
}
