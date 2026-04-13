<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Lineup;
use App\Form\Type\JsonCodeEditorType;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * EasyAdmin remote controller providing full CRUD management UI for Lineup records.
 */
class LineupCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Lineup::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('fantasyRound', 'Round');
        yield AssociationField::new('leagueMembership', 'Member');
        yield TextField::new('status');
        yield DateTimeField::new('submittedAt')->hideOnIndex();
        yield CodeEditorField::new('metadata', 'Slots')
            ->setFormType(JsonCodeEditorType::class)
            ->hideOnIndex();
        yield DateTimeField::new('updatedAt')->hideOnForm();
    }
}
