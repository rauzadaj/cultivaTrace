<?php

declare(strict_types=1);

namespace App\Doctrine\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Farm;
use App\Entity\Plant;
use App\Enum\PlantStatus;
use Doctrine\ORM\QueryBuilder;

/**
 * Excludes archived/inactive records from default API collection queries:
 *   - Farm: WHERE archivedAt IS NULL
 *   - Plant: WHERE status != 'archived'
 *
 * Archived records remain accessible via direct GET or custom admin queries;
 * they are simply hidden from the public collection endpoint by default.
 */
final class ArchiveFilterExtension implements QueryCollectionExtensionInterface
{
    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        $alias = $queryBuilder->getRootAliases()[0];

        if ($resourceClass === Farm::class) {
            $queryBuilder->andWhere($alias . '.archivedAt IS NULL');
            return;
        }

        if ($resourceClass === Plant::class) {
            $param = $queryNameGenerator->generateParameterName('archivedStatus');
            $queryBuilder
                ->andWhere($alias . '.status != :' . $param)
                ->setParameter($param, PlantStatus::ARCHIVED);
        }
    }
}
