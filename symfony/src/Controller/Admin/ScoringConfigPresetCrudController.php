<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ScoringConfigPreset;
use App\Form\Type\JsonCodeEditorType;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;

class ScoringConfigPresetCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ScoringConfigPreset::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('name', 'Preset Name');
        yield TextField::new('scoringConfigHash', 'Config Hash')->hideOnForm();
        yield CodeEditorField::new('scoringConfig', 'Configuration (JSON)')
            ->setFormType(JsonCodeEditorType::class)
            ->formatValue(fn ($value) => is_array($value) ? json_encode($value, JSON_PRETTY_PRINT) : $value)
            ->hideOnIndex();
    }
}
