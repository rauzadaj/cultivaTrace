<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Plant\Plant;
use App\Domain\Plant\PlantRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class PlantRepository implements PlantRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function findByOrganization(Uuid $orgId): array
    {
        return $this->entityManager
            ->createQueryBuilder()
            ->select('p')
            ->from(Plant::class, 'p')
            ->where('p.organizationId = :organizationId')
            ->setParameter('organizationId', $orgId)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findById(Uuid $id, Uuid $orgId): ?Plant
    {
        return $this->entityManager
            ->createQueryBuilder()
            ->select('p')
            ->from(Plant::class, 'p')
            ->where('p.id = :id')
            ->andWhere('p.organizationId = :organizationId')
            ->setParameter('id', $id)
            ->setParameter('organizationId', $orgId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
