<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\League;
use App\Entity\ScoringConfigPreset;
use Doctrine\ORM\EntityManagerInterface;
use Kibatic\DatagridBundle\Grid\GridBuilder;
use Kibatic\DatagridBundle\Grid\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller handling league-related operations such as listing, viewing, and creating leagues.
 */
#[Route('/leagues')]
class LeagueController extends AbstractController
{
    #[Route('/', name: 'app_league')]
    public function index(
        Request $request,
        GridBuilder $gridBuilder,
        EntityManagerInterface $entityManager
    ): Response {
        $queryBuilder = $entityManager->getRepository(League::class)
            ->createQueryBuilder('l')
            ->select('l', 't', 'count(m.id) as HIDDEN membershipCount')
            ->join('l.tournament', 't')
            ->leftJoin('l.memberships', 'm')
            ->groupBy('l.id, t.id')
            ->orderBy('membershipCount', 'DESC');

        // Fetch presets to map scoring config hash to name
        $presets = $entityManager->getRepository(ScoringConfigPreset::class)->findAll();
        $presetMap = [];
        foreach ($presets as $preset) {
            $presetMap[$preset->getScoringConfigHash()] = $preset->getName();
        }

        $grid = $gridBuilder->initialize($queryBuilder)
            ->addColumn('Sport', 'tournament.sportKey', template: 'league/_sport_icon.html.twig', sortable: 't.sportKey')
            ->addColumn('Tournament', 'tournament.name', sortable: 't.name', templateParameters: ['class' => 'fw-semibold'])
            ->addColumn('Name', 'name', sortable: 'l.name')
            ->addColumn('Players', fn(League $l) => $l->getMemberships()->count(), sortable: 'membershipCount', templateParameters: ['class' => 'text-center'])
            ->addColumn('Scoring', fn(League $l) => $presetMap[$l->getScoringConfigHash()] ?? 'Custom', templateParameters: ['class' => 'small ui-muted'])
            ->addColumn('Status', 'status', template: 'league/_status_badge.html.twig', sortable: 'l.status')
            ->addColumn('Actions', fn(League $l) => [
                [
                    'name' => 'View',
                    'url' => $this->generateUrl('app_league_view', ['id' => $l->getId()]),
                    'btn_type' => 'ui-btn ui-btn--ghost ui-btn--sm'
                ]
            ], template: Template::ACTIONS)
            ->getGrid();

        return $this->render('league/index.html.twig', [
            'grid' => $grid,
        ]);
    }

    #[Route('/{id}', name: 'app_league_view')]
    public function view(League $league): Response
    {
        return $this->render('league/view.html.twig', [
            'league' => $league,
        ]);
    }

    #[Route('/create', name: 'app_league_new')]
    public function create(): Response
    {
        // Placeholder for league creation
        return $this->render('league/view.html.twig', [
            'league' => null,
        ]);
    }
}
