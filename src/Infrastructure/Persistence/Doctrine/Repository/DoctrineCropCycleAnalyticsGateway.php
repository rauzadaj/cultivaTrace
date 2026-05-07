<?php

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Application\Cultivation\Analytics\CropCycleAnalyticsGateway;
use App\Application\Cultivation\Analytics\ReadModel\GeneticCycleAverage;
use App\Domain\Cultivation\Model\Crop;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @internal
 * @todo(mvp-deferred) Crop analytics gateway — deferred with the Crop domain.
 *                     See src/Domain/Cultivation/DEFERRED.md.
 */
final readonly class DoctrineCropCycleAnalyticsGateway implements CropCycleAnalyticsGateway
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function getAverageCycleDurations(?string $geneticId = null): array
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->from(Crop::class, 'crop')
            ->innerJoin('crop.genetic', 'genetic')
            ->select('genetic.id AS geneticId')
            ->addSelect('genetic.code AS geneticCode')
            ->addSelect('genetic.name AS geneticName')
            ->addSelect('COUNT(crop.id) AS completedCycles')
            ->addSelect('AVG(DATE_DIFF(crop.harvestedAt, crop.seededAt)) AS averageCycleDays')
            ->andWhere('crop.harvestedAt IS NOT NULL')
            ->groupBy('genetic.id, genetic.code, genetic.name')
            ->orderBy('averageCycleDays', 'ASC');

        if (null !== $geneticId) {
            $queryBuilder
                ->andWhere('genetic.id = :geneticId')
                ->setParameter('geneticId', $geneticId);
        }

        $rows = $queryBuilder->getQuery()->getArrayResult();

        return array_map(
            static fn (array $row): GeneticCycleAverage => new GeneticCycleAverage(
                geneticId: (string) $row['geneticId'],
                geneticCode: (string) $row['geneticCode'],
                geneticName: (string) $row['geneticName'],
                completedCycles: (int) $row['completedCycles'],
                averageCycleDays: (float) $row['averageCycleDays'],
            ),
            $rows,
        );
    }
}
