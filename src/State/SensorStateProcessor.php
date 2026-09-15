<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Sensor;
use App\Entity\User;
use App\Service\License\LicenseGuard;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @implements ProcessorInterface<Sensor, Sensor|null>
 */
final class SensorStateProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<Sensor, Sensor|null> $persistProcessor
     * @param ProcessorInterface<Sensor, null> $removeProcessor
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly LicenseGuard $licenseGuard,
        #[Autowire(service: 'api_platform.doctrine.orm.state.remove_processor')]
        private readonly ProcessorInterface $removeProcessor,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof Sensor) {
            $token = $this->tokenStorage->getToken();
            $user = $token?->getUser();

            if (!$user instanceof User) {
                throw new InvalidArgumentException('An authenticated user is required to create or update a sensor.');
            }

            if (!$user->hasOrganization()) {
                throw new InvalidArgumentException('The authenticated user must belong to an organization.');
            }

            $organization = $user->getOrganization();

            $this->licenseGuard->assertLicenseApproved($organization);

            if (isset($context['previous_data']) && $context['previous_data'] instanceof Sensor) {
                if ($context['previous_data']->getTenantId() != $organization->getId()) {
                    throw new AccessDeniedException('Cross-tenant sensor access is forbidden.');
                }
            }

            if ($data->getRoom()->getFarm()->getOrganization()->getId() != $organization->getId()) {
                throw new AccessDeniedException('The selected room does not belong to the authenticated organization.');
            }

            $data->setTenantId($organization->getId());
        }

        $processor = $operation instanceof Delete ? $this->removeProcessor : $this->persistProcessor;

        return $processor->process($data, $operation, $uriVariables, $context);
    }
}
