<?php

declare(strict_types=1);

namespace App\Service\Auth;

use Psr\Cache\CacheItemPoolInterface;

final readonly class LoginAttemptService
{
    private const WINDOW_SECONDS = 900;
    private const MAX_FAILURES = 5;

    public function __construct(
        private CacheItemPoolInterface $cache,
    ) {
    }

    public function isBlocked(string $email, ?\DateTimeImmutable $now = null): bool
    {
        $now ??= new \DateTimeImmutable();
        $state = $this->getState($email);

        if ($state['blockedUntil'] === null) {
            return false;
        }

        return $state['blockedUntil'] > $now->getTimestamp();
    }

    public function recordFailure(string $email, ?\DateTimeImmutable $now = null): void
    {
        $now ??= new \DateTimeImmutable();
        $item = $this->cache->getItem($this->buildKey($email));
        $state = $this->normalizeState($item->isHit() ? $item->get() : null, $now);

        $state['count']++;

        if ($state['count'] >= self::MAX_FAILURES && $state['blockedUntil'] === null) {
            $state['blockedUntil'] = $now->modify('+15 minutes')->getTimestamp();
        }

        $item->set($state);
        $item->expiresAfter(self::WINDOW_SECONDS);
        $this->cache->save($item);
    }

    public function clear(string $email): void
    {
        $this->cache->deleteItem($this->buildKey($email));
    }

    /**
     * @return array{count:int, blockedUntil:int|null}
     */
    private function getState(string $email): array
    {
        $item = $this->cache->getItem($this->buildKey($email));

        return $this->normalizeState($item->isHit() ? $item->get() : null, new \DateTimeImmutable());
    }

    /**
     * @param mixed $state
     *
     * @return array{count:int, blockedUntil:int|null}
     */
    private function normalizeState(mixed $state, \DateTimeImmutable $now): array
    {
        if (!is_array($state)) {
            return ['count' => 0, 'blockedUntil' => null];
        }

        $count = (int) ($state['count'] ?? 0);
        $blockedUntil = isset($state['blockedUntil']) ? (int) $state['blockedUntil'] : null;

        if ($blockedUntil !== null && $blockedUntil <= $now->getTimestamp()) {
            return ['count' => 0, 'blockedUntil' => null];
        }

        return ['count' => $count, 'blockedUntil' => $blockedUntil];
    }

    private function buildKey(string $email): string
    {
        return 'login_attempts_' . hash('sha256', mb_strtolower(trim($email)));
    }
}
