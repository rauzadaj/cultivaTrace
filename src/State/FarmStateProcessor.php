<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Farm;
use App\Entity\User;
use App\Service\License\LicenseGuard;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class FarmStateProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly LicenseGuard $licenseGuard,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof Farm) {
            $token = $this->tokenStorage->getToken();
            $user = $token?->getUser();

            if (!$user instanceof User) {
                throw new InvalidArgumentException('An authenticated user is required to create or update a farm.');
            }

            if (!$user->hasOrganization()) {
                throw new InvalidArgumentException('The authenticated user must belong to an organization.');
            }

            $organization = $user->getOrganization();

            if ($this->requiresLicenseApproval($operation)) {
                $this->licenseGuard->assertLicenseApproved($organization);
            }

            if (isset($context['previous_data']) && $context['previous_data'] instanceof Farm) {
                if ($context['previous_data']->getTenantId() != $organization->getId()) {
                    throw new AccessDeniedException('Cross-tenant farm access is forbidden.');
                }
            }

            $data->setOrganization($organization);
            $data->setTenantId($organization->getId());
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }

    private function requiresLicenseApproval(Operation $operation): bool
    {
        return in_array(strtoupper((string) $operation->getMethod()), ['POST', 'PUT', 'PATCH'], true);
    }
}
