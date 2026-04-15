<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Participant;
use App\Form\Type\JsonCodeEditorType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\ScoringConfigPreset;

/**
 * EasyAdmin remote controller providing full CRUD management UI for Participant records.
 */
class ParticipantCrudController extends AbstractCrudController
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public static function getEntityFqcn(): string
    {
        return Participant::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        // Participants are managed by the Python ingestion pipeline; disable create/edit/delete in admin
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->disable(Action::NEW, Action::EDIT, Action::DELETE);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setDefaultSort(['name' => 'ASC'])
            ->setSearchFields(['name', 'teamName', 'externalId'])
            ->overrideTemplate('crud/detail', 'admin/participant_detail.html.twig');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('name');
        yield TextField::new('sport');
        yield TextField::new('type');
        yield TextField::new('teamName', 'Team');
        yield TextField::new('position');
        yield AssociationField::new('team');
        yield TextField::new('injuryStatus');
        yield TextField::new('externalId', 'External ID')->hideOnIndex();
        yield CodeEditorField::new('metadata')
            ->setFormType(JsonCodeEditorType::class)
            ->formatValue(fn ($value) => is_array($value) ? json_encode($value, JSON_PRETTY_PRINT) : $value)
            ->hideOnIndex();
    }

    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        if ($responseParameters->get('pageName') === Crud::PAGE_DETAIL) {
            $presets = $this->em->getRepository(ScoringConfigPreset::class)->findAll();
            $responseParameters->set('scoring_presets', $presets);

            $conn = $this->em->getConnection();

            $maxStats = [];
            $maxSql = "SELECT sd.code, MAX(ps.total_value) as max_tot, MAX(ps.average_value) as max_avg FROM participant_stats ps JOIN stat_definitions sd ON ps.stat_definition_id = sd.id GROUP BY sd.code";
            $maxResult = $conn->executeQuery($maxSql)->fetchAllAssociative();
            foreach ($maxResult as $row) {
                $maxStats[$row['code']] = [
                    'total' => (float) $row['max_tot'],
                    'average' => (float) $row['max_avg']
                ];
            }
            $responseParameters->set('max_stats', $maxStats);

            $benchmanStats = [];
            $benchSql = "SELECT sd.code, ps.total_value as tot_val, ps.average_value as avg_val FROM participant_stats ps JOIN stat_definitions sd ON ps.stat_definition_id = sd.id JOIN participants p ON ps.participant_id = p.id WHERE p.external_id = 'clifford_benchman'";
            $benchResult = $conn->executeQuery($benchSql)->fetchAllAssociative();
            foreach ($benchResult as $row) {
                $benchmanStats[$row['code']] = [
                    'total' => (float) $row['tot_val'],
                    'average' => (float) $row['avg_val']
                ];
            }
            $responseParameters->set('benchman_stats', $benchmanStats);
        }

        return $responseParameters;
    }
}
