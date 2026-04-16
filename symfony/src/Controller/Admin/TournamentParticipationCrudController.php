<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\TournamentParticipation;
use App\Form\Type\JsonCodeEditorType;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;

/**
 * EasyAdmin CRUD for TournamentParticipation records.
 *
 * Populated automatically by the Python ingester (make ingest-nba-participations).
 * Use this admin to review or override statuses manually.
 */
class TournamentParticipationCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return TournamentParticipation::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('tournament');
        yield AssociationField::new('team');
        yield ChoiceField::new('status')
            ->setChoices([
                'Active (still competing)'  => 'active',
                'Eliminated'                => 'eliminated',
                'Champion'                  => 'champion',
                'Play-in'                   => 'playin',
            ])
            ->allowMultipleChoices(false)
            ->setRequired(true);
        yield IntegerField::new('seed')->setRequired(false);
        yield CodeEditorField::new('metadata')
            ->setFormType(JsonCodeEditorType::class)
            ->hideOnIndex();
    }
}
