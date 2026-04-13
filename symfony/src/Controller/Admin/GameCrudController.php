<?php

namespace App\Controller\Admin;

use App\Entity\Game;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class GameCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Game::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('externalId'),
            AssociationField::new('tournament'),
            AssociationField::new('tournamentRound'),
            DateTimeField::new('date'),
            AssociationField::new('homeTeam'),
            AssociationField::new('awayTeam'),
            IntegerField::new('homeScore'),
            IntegerField::new('awayScore'),
            AssociationField::new('winnerTeam'),
            TextField::new('status'),
        ];
    }
}
