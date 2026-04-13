<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Round;
use App\Form\Type\JsonCodeEditorType;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class RoundCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Round::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('tournament');
        yield IntegerField::new('orderIndex', 'Order');
        yield TextField::new('name');
        yield TextField::new('canonicalKey', 'Key')->hideOnIndex();
        yield AssociationField::new('homeTeam')->hideOnIndex();
        yield AssociationField::new('awayTeam')->hideOnIndex();
        yield TextField::new('status');
        yield CodeEditorField::new('metadata')
            ->setFormType(JsonCodeEditorType::class)
            ->hideOnIndex();
    }
}
