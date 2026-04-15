<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Game;
use App\Entity\Participant;
use App\Entity\Tournament;
use Doctrine\ORM\EntityManagerInterface;
use Kibatic\DatagridBundle\Grid\GridBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Handles the generation of tournament statistics for a given tournament.
 *
 * This method retrieves participants and associated teams involved in any game of the specified tournament.
 * The results are processed to generate a grid with columns including participant details and rendered into a
 * statistics template for display.
 *
 * @param Tournament $tournament The tournament for which statistics are displayed.
 * @param Request $request The HTTP request object.
 * @param GridBuilder $gridBuilder The grid builder utility for creating display grids.
 * @param EntityManagerInterface $entityManager The entity manager for database interactions.
 *
 * @return Response The rendered response containing the tournament statistics page.
 */
class StatisticsController extends AbstractController
{
    #[Route('/tournaments/{slug}/statistics', name: 'app_tournament_stats')]
    public function tournamentStats(
        Tournament $tournament,
        Request $request,
        GridBuilder $gridBuilder,
        EntityManagerInterface $entityManager
    ): Response {
        // Query participants related with teams which are listed in any game of the tournament.
        $participantsQuery = $entityManager->getRepository(Participant::class)
            ->createQueryBuilder('p')
            ->select('p', 't')
            ->innerJoin('p.team', 't')
            ->innerJoin(Game::class, 'g', 'WITH', 'g.homeTeam = t OR g.awayTeam = t')
            ->where('g.tournament = :tournament')
            ->setParameter('tournament', $tournament)
            ->groupBy('p.id, t.id')
            ->orderBy('p.name', 'ASC');

        $grid = $gridBuilder->initialize($participantsQuery)
            ->addColumn('Name', fn(Participant $p) => $p->getName(), sortable: 'p.name', templateParameters: ['class' => 'fw-bold text-white'])
            ->addColumn('Team', fn(Participant $p, array $extra) => isset($extra[1]) ? $extra[1]->getName() : 'N/A', sortable: 't.name', templateParameters: ['class' => 'ui-muted'])
            ->addColumn('Position', fn(Participant $p) => $p->getPosition(), sortable: 'p.position', templateParameters: ['class' => 'small text-uppercase'])
            ->addColumn('Sport', fn(Participant $p) => $p->getSport(), templateParameters: ['class' => 'text-capitalize small'])
            ->addColumn('Health', fn(Participant $p) => $p->getInjuryStatus() ?: 'Active')
            ->getGrid();

        return $this->render('statistics/tournament.html.twig', [
            'tournament' => $tournament,
            'grid' => $grid,
        ]);
    }
}
