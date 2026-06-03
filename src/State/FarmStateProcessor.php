<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Metadata\Post;
use App\Entity\Farm;
use App\Entity\User;
use App\Service\License\LicenseGuard;
use App\Service\PlanLimitExceededException;
use App\Service\PlanLimitsService;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class FarmStateProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly LicenseGuard $licenseGuard,
        private readonly EntityManagerInterface $em,
        private readonly PlanLimitsService $planLimits,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof Farm) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();

        if (!$user instanceof User) {
            throw new InvalidArgumentException('An authenticated user is required to create or update a farm.');
        }

        if (!$user->hasOrganization()) {
            throw new InvalidArgumentException('The authenticated user must belong to an organization.');
        }

        $organization = $user->getOrganization();

        // Soft-delete: mark archivedAt instead of removing the row
        if ($operation instanceof Delete) {
            if ($data->getTenantId() != $organization->getId()) {
                throw new AccessDeniedException('Cross-tenant farm access is forbidden.');
            }
            $data->setArchivedAt(new \DateTimeImmutable());
            $this->em->flush();
            return null;
        }

        if ($this->requiresLicenseApproval($operation)) {
            $this->licenseGuard->assertLicenseApproved($organization);
        }

        if ($operation instanceof Post) {
            try {
                $this->planLimits->checkFarmLimit($organization);
            } catch (PlanLimitExceededException $exception) {
                throw new HttpException(
                    402,
                    json_encode($exception->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: $exception->getMessage(),
                    $exception,
                );
            }
        }

        if (isset($context['previous_data']) && $context['previous_data'] instanceof Farm) {
            if ($context['previous_data']->getTenantId() != $organization->getId()) {
                throw new AccessDeniedException('Cross-tenant farm access is forbidden.');
            }
        }

        $data->setOrganization($organization);
        $data->setTenantId($organization->getId());

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }

    private function requiresLicenseApproval(Operation $operation): bool
    {
        return in_array(strtoupper((string) $operation->getMethod()), ['POST', 'PUT', 'PATCH'], true);
    }
}
