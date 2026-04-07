<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Alert;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Alert>
 */
final class AlertRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Alert::class);
    }

    /**
     * @return list<Alert>
     */
    public function findUnreadForTenant(Uuid $tenantId, int $limit = 20): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.tenantId = :tenantId')
            ->andWhere('a.acknowledgedAt IS NULL')
            ->setParameter('tenantId', $tenantId, 'uuid')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
