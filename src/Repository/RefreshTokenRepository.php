<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RefreshToken;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Refresh tokens are stored server-side so they can be revoked individually or globally.
 */
final class RefreshTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RefreshToken::class);
    }

    public function findActiveByTokenHash(string $tokenHash, \DateTimeImmutable $now): ?RefreshToken
    {
        return $this->createQueryBuilder('rt')
            ->andWhere('rt.tokenHash = :tokenHash')
            ->andWhere('rt.revokedAt IS NULL')
            ->andWhere('rt.expiresAt > :now')
            ->setParameter('tokenHash', $tokenHash)
            ->setParameter('now', $now)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findActiveById(int $id, \DateTimeImmutable $now): ?RefreshToken
    {
        return $this->createQueryBuilder('rt')
            ->andWhere('rt.id = :id')
            ->andWhere('rt.revokedAt IS NULL')
            ->andWhere('rt.expiresAt > :now')
            ->setParameter('id', $id)
            ->setParameter('now', $now)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return int[]
     */
    public function findActiveIdsByUser(User $user, \DateTimeImmutable $now): array
    {
        return array_column(
            $this->createQueryBuilder('rt')
                ->select('rt.id')
                ->andWhere('rt.user = :user')
                ->andWhere('rt.revokedAt IS NULL')
                ->andWhere('rt.expiresAt > :now')
                ->setParameter('user', $user)
                ->setParameter('now', $now)
                ->getQuery()
                ->getArrayResult(),
            'id',
        );
    }

    public function revokeAllForUser(User $user, ?\DateTimeImmutable $revokedAt = null): int
    {
        return $this->createQueryBuilder('rt')
            ->update()
            ->set('rt.revokedAt', ':revokedAt')
            ->andWhere('rt.user = :user')
            ->andWhere('rt.revokedAt IS NULL')
            ->setParameter('user', $user)
            ->setParameter('revokedAt', $revokedAt ?? new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }

    public function purgeExpired(\DateTimeImmutable $now): int
    {
        return $this->createQueryBuilder('rt')
            ->delete()
            ->andWhere('rt.expiresAt <= :now')
            ->setParameter('now', $now)
            ->getQuery()
            ->execute();
    }
}
