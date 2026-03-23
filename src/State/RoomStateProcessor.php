<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Room;
use App\Entity\User;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class RoomStateProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof Room) {
            $token = $this->tokenStorage->getToken();
            $user = $token?->getUser();

            if (!$user instanceof User) {
                throw new InvalidArgumentException('An authenticated user is required to create or update a room.');
            }

            $organization = $user->getOrganization();
            if ($organization === null) {
                throw new InvalidArgumentException('The authenticated user must belong to an organization.');
            }

            if ($data->getFarm()->getOrganization()->getId() != $organization->getId()) {
                throw new InvalidArgumentException('The selected farm does not belong to the authenticated organization.');
            }

            $data->setTenantId($organization->getId());
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
