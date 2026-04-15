<?php

declare(strict_types=1);

namespace App\Doctrine\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Participant;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\RequestStack;

final class ParticipantScoreQueryExtension implements QueryCollectionExtensionInterface
{
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        Operation $operation = null,
        array $context = []
    ): void {
        $this->apply($queryBuilder, $resourceClass);
    }

    private function apply(QueryBuilder $queryBuilder, string $resourceClass): void
    {
        if (Participant::class !== $resourceClass) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return;
        }

        $sort = $request->query->get('sort');
        $configHash = $request->query->get('config_hash');

        if ('score' === $sort && $configHash) {
            $rootAlias = $queryBuilder->getRootAliases()[0];
            
            // Join aggregatedScores only for the specific config hash
            $queryBuilder->leftJoin(
                sprintf('%s.aggregatedScores', $rootAlias),
                'score',
                'WITH',
                'score.scoreConfigHash = :configHash AND score.statScope = :statScope'
            )
            ->setParameter('configHash', $configHash)
            ->setParameter('statScope', 'season')
            ->orderBy('score.totalScore', 'DESC');
        } elseif ($configHash) {
             // If we just want to filter without sorting specifically
            $rootAlias = $queryBuilder->getRootAliases()[0];
            $queryBuilder->leftJoin(
                sprintf('%s.aggregatedScores', $rootAlias),
                'score',
                'WITH',
                'score.scoreConfigHash = :configHash AND score.statScope = :statScope'
            )
            ->setParameter('configHash', $configHash)
            ->setParameter('statScope', 'season');
        }
    }
}
