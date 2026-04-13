<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\League;
use App\Form\Type\JsonCodeEditorType;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * EasyAdmin remote controller providing full CRUD management UI for League records.
 */
class LeagueCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return League::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('name');
        yield AssociationField::new('tournament');
        yield AssociationField::new('commissioner');
        yield ChoiceField::new('status')->setChoices([
            'Forming'   => 'forming',
            'Draft'     => 'draft',
            'Active'    => 'active',
            'Completed' => 'completed',
        ]);
        yield CodeEditorField::new('settings')
            ->setFormType(JsonCodeEditorType::class)
            ->hideOnIndex();
        yield CodeEditorField::new('lineupTemplate', 'Lineup Template')
            ->setFormType(JsonCodeEditorType::class)
            ->hideOnIndex();
        yield DateTimeField::new('createdAt')->hideOnForm();
    }
}
