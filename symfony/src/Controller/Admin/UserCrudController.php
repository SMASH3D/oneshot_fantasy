<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * EasyAdmin CRUD controller for managing User records in the admin panel.
 */
class UserCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield EmailField::new('email');
        yield TextField::new('nickname');
        yield ChoiceField::new('roles')
            ->setChoices([
                'User'  => User::ROLE_USER,
                'Pro'   => User::ROLE_PRO,
                'API'   => User::ROLE_API,
                'Admin' => User::ROLE_ADMIN,
            ])
            ->allowMultipleChoices()
            ->renderAsBadges([
                User::ROLE_USER  => 'secondary',
                User::ROLE_PRO   => 'primary',
                User::ROLE_API   => 'info',
                User::ROLE_ADMIN => 'danger',
            ]);
        yield DateTimeField::new('createdAt')->hideOnForm();
    }
}
