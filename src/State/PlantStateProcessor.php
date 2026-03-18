<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Plant;
use App\Entity\User;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class PlantStateProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof Plant) {
            $token = $this->tokenStorage->getToken();
            $user = $token?->getUser();

            if (!$user instanceof User) {
                throw new InvalidArgumentException('An authenticated user is required to create or update a plant.');
            }

            $organization = $user->getOrganization();
            if ($organization === null) {
                throw new InvalidArgumentException('The authenticated user must belong to an organization.');
            }

            $data->setTenantId($organization->getId());

            if (!isset($context['previous_data']) || !$context['previous_data'] instanceof Plant) {
                $data->setCreatedBy($user);
            }
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
