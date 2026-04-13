<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\FantasyRound;
use App\Form\Type\JsonCodeEditorType;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * EasyAdmin remote controller providing full CRUD management UI for FantasyRound records.
 */
class FantasyRoundCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return FantasyRound::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('league');
        yield AssociationField::new('tournamentRound', 'Tournament Round')->hideOnIndex();
        yield IntegerField::new('orderIndex', 'Order');
        yield TextField::new('name');
        yield DateTimeField::new('opensAt', 'Opens');
        yield DateTimeField::new('locksAt', 'Locks');
        yield CodeEditorField::new('metadata')
            ->setFormType(JsonCodeEditorType::class)
            ->hideOnIndex();
    }
}
