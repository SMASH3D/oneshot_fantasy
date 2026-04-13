<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\LeagueMembership;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * EasyAdmin remote controller providing full CRUD management UI for LeagueMembership records.
 */
class LeagueMembershipCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return LeagueMembership::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('league');
        yield AssociationField::new('user');
        yield TextField::new('nickname');
        yield TextField::new('role');
        yield DateTimeField::new('joinedAt')->hideOnForm();
    }
}
