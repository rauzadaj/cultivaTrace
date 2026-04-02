<?php

declare(strict_types=1);

namespace App\Security;

use App\Enum\UserAccountStatus;
use App\Repository\RefreshTokenRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class JwtDecodedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private RefreshTokenRepository $refreshTokenRepository,
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

        $refreshToken = $this->refreshTokenRepository->findActiveById((int) $payload['sid'], new \DateTimeImmutable());

        if ($refreshToken === null) {
            $event->markAsInvalid();

            return;
        }

        $user = $refreshToken->getUser();
        $organization = $user->getOrganization();

        if (
            $user->getId() !== (int) $payload['userId']
            || $organization === null
            || (string) $organization->getId() !== (string) $payload['tenantId']
            || $organization->isSuspended()
            || $user->getAccountStatus() !== UserAccountStatus::ACTIVE
        ) {
            $event->markAsInvalid();
        }
    }
}
