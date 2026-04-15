<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Tournament;
use App\Enum\BracketType;
use App\Form\Type\JsonCodeEditorType;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * EasyAdmin remote controller providing full CRUD management UI for Tournament records.
 */
class TournamentCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Tournament::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('name');
        yield TextField::new('slug');
        yield TextField::new('sportKey', 'Sport');
        yield TextField::new('statsAdapterKey', 'Stats Adapter')->hideOnIndex();
        yield TextField::new('defaultScoringEngineKey', 'Scoring Engine')->hideOnIndex();
        yield TextField::new('timezone')->hideOnIndex();
        yield TextField::new('status');
        yield ChoiceField::new('bracketType', 'Bracket Type')
            ->setChoices([
                'NBA Postseason tournament' => BracketType::NbaPostseason,
                'Single-Elimination Bracket' => BracketType::SingleElimination,
                'Double-Elimination Bracket' => BracketType::DoubleElimination,
                'Group Stage Bracket' => BracketType::GroupStage,
            ])
            ->allowMultipleChoices(false)
            ->setRequired(false)
            ->hideOnIndex();
        yield DateTimeField::new('startsAt', 'Starts');
        yield DateTimeField::new('endsAt', 'Ends')->hideOnIndex();
        yield CodeEditorField::new('metadata')
            ->setFormType(JsonCodeEditorType::class)
            ->hideOnIndex();
        yield CodeEditorField::new('cmsContent', 'CMS Content')
            ->setLanguage('twig')
            ->hideOnIndex();
        yield DateTimeField::new('createdAt')->hideOnForm();
    }
}
