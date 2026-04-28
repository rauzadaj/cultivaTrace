<?php

declare(strict_types=1);

namespace App\Infrastructure\Monitoring;

use App\Entity\User;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class SentryTenantListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly HubInterface $hub,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', -20]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof User || !$user->hasOrganization()) {
            return;
        }

        $this->hub->configureScope(function (Scope $scope) use ($user): void {
            $scope->setTag('tenant_id', (string) $user->getOrganization()->getId());
            $scope->setUser(['id' => (string) $user->getId(), 'email' => $user->getEmail()]);
        });
    }
}
