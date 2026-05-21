<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Organization;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class OrganizationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Organization::class);
    }

    /**
     * Case-insensitive name lookup with LIMIT 1 to avoid NonUniqueResultException
     * when legacy data contains duplicate lowercased names (no DB-level CI constraint yet).
     */
    public function findByNameInsensitive(string $name): ?Organization
    {
        return $this->createQueryBuilder('o')
            ->where('LOWER(o.name) = LOWER(:name)')
            ->setParameter('name', $name)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
