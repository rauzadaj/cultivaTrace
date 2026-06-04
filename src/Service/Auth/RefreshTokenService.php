<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\RefreshTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\CacheInterface;

final readonly class RefreshTokenService
{
    private const TOKEN_TTL = '7 days';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private RefreshTokenRepository $refreshTokenRepository,
        private TokenHasher $tokenHasher,
        private ?CacheInterface $cache = null,
    ) {
    }

    /**
     * @return array{plainToken: string, refreshToken: RefreshToken}
     */
    public function issue(User $user, ?\DateTimeImmutable $issuedAt = null): array
    {
        $issuedAt ??= new \DateTimeImmutable();
        $plainToken = bin2hex(random_bytes(32));

        $refreshToken = (new RefreshToken())
            ->setUser($user)
            ->setTokenHash($this->tokenHasher->hash($plainToken))
            ->setExpiresAt($issuedAt->modify('+' . self::TOKEN_TTL));

        $this->entityManager->persist($refreshToken);
        $this->entityManager->flush();

        return [
            'plainToken' => $plainToken,
            'refreshToken' => $refreshToken,
        ];
    }

    public function findActive(string $plainToken, ?\DateTimeImmutable $now = null): ?RefreshToken
    {
        $now ??= new \DateTimeImmutable();

        return $this->refreshTokenRepository->findActiveByTokenHash(
            $this->tokenHasher->hash($plainToken),
            $now,
        );
    }

    /**
     * @return array{plainToken: string, refreshToken: RefreshToken}|null
     */
    public function rotate(string $plainToken, ?\DateTimeImmutable $now = null): ?array
    {
        $now ??= new \DateTimeImmutable();
        $currentToken = $this->findActive($plainToken, $now);

        if ($currentToken === null) {
            return null;
        }

        $currentToken->revoke($now);
        $this->cache?->delete('rt_valid_' . $currentToken->getId());
        $replacement = $this->issue($currentToken->getUser(), $now);
        $this->entityManager->flush();

        return $replacement;
    }

    public function revoke(RefreshToken $refreshToken, ?\DateTimeImmutable $revokedAt = null): void
    {
        $refreshToken->revoke($revokedAt);
        $this->entityManager->flush();
        $this->cache?->delete('rt_valid_' . $refreshToken->getId());
    }

    public function revokeAllForUser(User $user, ?\DateTimeImmutable $revokedAt = null): int
    {
        return $this->refreshTokenRepository->revokeAllForUser($user, $revokedAt);
    }

    public function purgeExpired(?\DateTimeImmutable $now = null): int
    {
        return $this->refreshTokenRepository->purgeExpired($now ?? new \DateTimeImmutable());
    }
}
