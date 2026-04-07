<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Strain;
use App\Entity\User;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class StrainStateProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof Strain) {
            $token = $this->tokenStorage->getToken();
            $user = $token?->getUser();

            if (!$user instanceof User) {
                throw new InvalidArgumentException('An authenticated user is required to create or update a strain.');
            }

            if (!$user->hasOrganization()) {
                throw new InvalidArgumentException('The authenticated user must belong to an organization.');
            }

            $organization = $user->getOrganization();

            if (isset($context['previous_data']) && $context['previous_data'] instanceof Strain) {
                if ($context['previous_data']->getTenantId() != $organization->getId()) {
                    throw new AccessDeniedException('Cross-tenant strain access is forbidden.');
                }
            }

            $data->setTenantId($organization->getId());
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
