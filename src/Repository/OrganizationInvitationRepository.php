<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Organization;
use App\Entity\OrganizationInvitation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;

/**
 * @extends ServiceEntityRepository<OrganizationInvitation>
 */
final class OrganizationInvitationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrganizationInvitation::class);
    }

    public function findActiveByTokenHash(string $tokenHash, \DateTimeImmutable $now): ?OrganizationInvitation
    {
        return $this->createQueryBuilder('inv')
            ->andWhere('inv.tokenHash = :tokenHash')
            ->andWhere('inv.acceptedAt IS NULL')
            ->andWhere('inv.expiresAt > :now')
            ->setParameter('tokenHash', $tokenHash)
            ->setParameter('now', $now)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<OrganizationInvitation> */
    public function findPendingForOrganization(Organization $organization): array
    {
        return $this->createQueryBuilder('inv')
            ->andWhere('inv.organization = :organization')
            ->andWhere('inv.acceptedAt IS NULL')
            ->setParameter('organization', $organization->getId(), UuidType::NAME)
            ->orderBy('inv.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
