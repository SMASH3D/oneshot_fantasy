<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;

/**
 * Standard web controller establishing core framework endpoints for Dashboard routes.
 */
#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Oneshot Fantasy')
            ->setFaviconPath('favicon.ico')
            ->renderContentMaximized();
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::linkToUrl('API Docs', 'fa fa-book', '/api/docs');
        yield MenuItem::section('Tournaments');
        yield MenuItem::linkTo(TournamentCrudController::class, 'Tournaments', 'fa fa-trophy');
        yield MenuItem::linkTo(TournamentParticipationCrudController::class, 'Team Participations', 'fa fa-medal');
        yield MenuItem::linkTo(TeamCrudController::class, 'Teams', 'fa fa-flag');
        yield MenuItem::linkTo(RoundCrudController::class, 'Rounds', 'fa fa-list-ol');
        yield MenuItem::linkTo(GameCrudController::class, 'Games', 'fa fa-gamepad');
        yield MenuItem::linkTo(ParticipantCrudController::class, 'Participants', 'fa fa-users');
        yield MenuItem::section('Leagues');
        yield MenuItem::linkTo(LeagueCrudController::class, 'Leagues', 'fa fa-shield');
        yield MenuItem::linkTo(LeagueMembershipCrudController::class, 'Memberships', 'fa fa-user-plus');
        yield MenuItem::linkTo(FantasyRoundCrudController::class, 'Fantasy Rounds', 'fa fa-calendar');
        yield MenuItem::linkTo(LineupCrudController::class, 'Lineups', 'fa fa-list');
        yield MenuItem::linkTo(ScoringConfigPresetCrudController::class, 'Scoring Presets', 'fa fa-cogs');
        yield MenuItem::linkTo(ScoreCrudController::class, 'Scores', 'fa fa-star');
        yield MenuItem::section('Draft');
        yield MenuItem::linkTo(DraftSessionCrudController::class, 'Draft Sessions', 'fa fa-clock');
        yield MenuItem::linkTo(DraftPickCrudController::class, 'Draft Picks', 'fa fa-hand-pointer');
        yield MenuItem::section('Users');
        yield MenuItem::linkTo(UserCrudController::class, 'Users', 'fa fa-user');
    }
}
