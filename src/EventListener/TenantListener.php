<?php

namespace App\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use App\Entity\User;

/**
 * TenantListener — s'exécute sur chaque requête HTTP authentifiée.
 *
 * Il extrait le tenantId depuis l'utilisateur connecté (via JWT)
 * et l'injecte dans le TenantFilter Doctrine.
 *
 * Enregistrer comme service dans config/services.yaml :
 *
 *   App\EventListener\TenantListener:
 *     tags:
 *       - { name: kernel.event_listener, event: kernel.request, priority: 10 }
 */
class TenantListener
{
    public function __construct(
        private EntityManagerInterface $em,
        private TokenStorageInterface $tokenStorage,
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $filters = $this->em->getFilters();

        $token = $this->tokenStorage->getToken();
        if ($token === null) {
            if ($filters->isEnabled('tenant_filter')) {
                $filters->disable('tenant_filter');
            }

            return;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            if ($filters->isEnabled('tenant_filter')) {
                $filters->disable('tenant_filter');
            }

            return;
        }

        if (!$user->hasOrganization()) {
            if ($filters->isEnabled('tenant_filter')) {
                $filters->disable('tenant_filter');
            }

            throw new AccessDeniedHttpException('Authenticated user must belong to an organization.');
        }

        $organization = $user->getOrganization();

        $filter = $filters->isEnabled('tenant_filter')
            ? $filters->getFilter('tenant_filter')
            : $filters->enable('tenant_filter');
        $filter->setParameter('tenantId', (string) $organization->getId());

        if ($organization->isSuspended()) {
            throw new AccessDeniedHttpException('Organization access is suspended.');
        }
    }
}
