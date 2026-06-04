<?php

declare(strict_types=1);

namespace App\Security;

use App\Enum\UserAccountStatus;
use App\Repository\RefreshTokenRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final readonly class JwtDecodedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private RefreshTokenRepository $refreshTokenRepository,
        private CacheInterface $cache,
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

        $sid = (int) $payload['sid'];

        /** @var array{userId: int, tenantId: string}|null $cached */
        $cached = $this->cache->get('rt_valid_' . $sid, function (ItemInterface $item) use ($sid): ?array {
            $item->expiresAfter(60);

            $refreshToken = $this->refreshTokenRepository->findActiveById($sid, new \DateTimeImmutable());
            if ($refreshToken === null || !$refreshToken->getUser()->hasOrganization()) {
                return null;
            }

            $user = $refreshToken->getUser();
            $org  = $user->getOrganization();

            if ($org->isSuspended() || $user->getAccountStatus() !== UserAccountStatus::ACTIVE) {
                return null;
            }

            return ['userId' => $user->getId(), 'tenantId' => (string) $org->getId()];
        });

        if (
            $cached === null
            || $cached['userId'] !== (int) $payload['userId']
            || $cached['tenantId'] !== (string) $payload['tenantId']
        ) {
            $event->markAsInvalid();
        }
    }
}
