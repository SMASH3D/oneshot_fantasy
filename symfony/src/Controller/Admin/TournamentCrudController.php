<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Tournament;
use App\Enum\BracketType;
use App\Form\Type\JsonCodeEditorType;
use App\Service\BracketContentFetcher;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

/**
 * EasyAdmin CRUD for Tournament records.
 *
 * Custom action: "Fetch ESPN Bracket" — calls BracketContentFetcher to populate
 * the tournament's cms_content field with an ESPN bracket link block.
 */
class TournamentCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly BracketContentFetcher $bracketFetcher,
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {}

    public static function getEntityFqcn(): string
    {
        return Tournament::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        $fetchBracket = Action::new('fetchEspnBracket', 'Fetch ESPN Bracket', 'fa fa-diagram-3')
            ->linkToCrudAction('fetchEspnBracket')
            ->addCssClass('btn btn-sm btn-outline-info')
            ->displayIf(static fn(Tournament $t): bool => $t->getBracketType() !== null);

        return $actions
            ->add(Crud::PAGE_INDEX, $fetchBracket)
            ->add(Crud::PAGE_DETAIL, $fetchBracket);
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
                'NBA Postseason tournament'   => BracketType::NbaPostseason,
                'Single-Elimination Bracket' => BracketType::SingleElimination,
                'Double-Elimination Bracket' => BracketType::DoubleElimination,
                'Group Stage Bracket'        => BracketType::GroupStage,
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

    /**
     * Custom action: fetch ESPN bracket URL and populate cms_content.
     */
    public function fetchEspnBracket(AdminContext $context): Response
    {
        /** @var Tournament $tournament */
        $tournament = $context->getEntity()->getInstance();

        $result = $this->bracketFetcher->fetchAndStore($tournament);

        $this->addFlash('success', $result);

        $url = $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Crud::PAGE_INDEX)
            ->generateUrl();

        return $this->redirect($url);
    }
}
