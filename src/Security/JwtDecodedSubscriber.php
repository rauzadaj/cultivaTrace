<?php

declare(strict_types=1);

namespace App\Security;

use App\Enum\UserAccountStatus;
use App\Repository\RefreshTokenRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class JwtDecodedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private RefreshTokenRepository $refreshTokenRepository,
        #[Autowire(service: 'cache.app')]
        private CacheItemPoolInterface $cache,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            Events::JWT_DECODED => 'onJwtDecoded',
        ];
    }

    public function onJwtDecoded(JWTDecodedEvent $event): void
    {
        $payload = $event->getPayload();

        if (!isset($payload['sid'], $payload['userId'], $payload['tenantId'])) {
            return;
        }

        $sid  = (int) $payload['sid'];
        $item = $this->cache->getItem('rt_valid_' . $sid);

        if ($item->isHit()) {
            /** @var array{userId: int, tenantId: string} $cached */
            $cached = $item->get();
        } else {
            $refreshToken = $this->refreshTokenRepository->findActiveById($sid, new \DateTimeImmutable());

            if ($refreshToken === null || !$refreshToken->getUser()->hasOrganization()) {
                $event->markAsInvalid();
                return;
            }

            $user = $refreshToken->getUser();
            $org  = $user->getOrganization();

            if ($org->isSuspended() || $user->getAccountStatus() !== UserAccountStatus::ACTIVE) {
                $event->markAsInvalid();
                return;
            }

            $cached = ['userId' => $user->getId(), 'tenantId' => (string) $org->getId()];

            // Only cache valid results — invalid tokens always hit the DB to avoid stale cache across test resets
            $item->set($cached)->expiresAfter(60);
            $this->cache->save($item);
        }

        if (
            $cached['userId'] !== (int) $payload['userId']
            || $cached['tenantId'] !== (string) $payload['tenantId']
        ) {
            $event->markAsInvalid();
        }
    }
}
