<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\DraftPick;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;

class DraftPickCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return DraftPick::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->disable(Action::NEW, Action::EDIT);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('draftSession', 'Session');
        yield IntegerField::new('pickIndex', '#');
        yield AssociationField::new('leagueMembership', 'Member');
        yield AssociationField::new('participant');
        yield DateTimeField::new('createdAt')->hideOnForm();
    }
}
