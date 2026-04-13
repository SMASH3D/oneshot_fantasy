<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Team;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * EasyAdmin remote controller providing full CRUD management UI for Team records.
 */
class TeamCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Team::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('externalId'),
            TextField::new('name'),
            TextField::new('city'),
            TextField::new('abbreviation'),
            TextField::new('sport'),
        ];
    }
}
