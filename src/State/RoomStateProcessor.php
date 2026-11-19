<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Room;
use App\Entity\User;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\License\LicenseGuard;
use App\Service\PlanLimitExceededException;
use App\Service\PlanLimitsService;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * @implements ProcessorInterface<Room, Room|null>
 */
final class RoomStateProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<Room, Room|null> $persistProcessor
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly LicenseGuard $licenseGuard,
        private readonly PlanLimitsService $planLimits,
        private readonly EntityManagerInterface $entityManager,
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

            if (!$user->hasOrganization()) {
                throw new InvalidArgumentException('The authenticated user must belong to an organization.');
            }

            $organization = $user->getOrganization();

            $this->licenseGuard->assertLicenseApproved($organization);

            $farm = $data->getFarm();
            $this->entityManager->beginTransaction();
            try {
                $this->entityManager->lock($organization, LockMode::PESSIMISTIC_WRITE);
                $this->entityManager->refresh($organization);
                $this->entityManager->lock($farm, LockMode::PESSIMISTIC_WRITE);
                $this->entityManager->refresh($farm);
                if ($farm->isArchived()) {
                    throw new InvalidArgumentException('Cannot create or update a room in an archived farm.');
                }

                if (!isset($context['previous_data'])) {
                    try {
                        $this->planLimits->checkRoomLimit($organization);
                    } catch (PlanLimitExceededException $exception) {
                        throw new HttpException(
                            402,
                            json_encode($exception->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: $exception->getMessage(),
                            $exception,
                        );
                    }
                }

                if ($farm->getOrganization()->getId() != $organization->getId()) {
                    throw new InvalidArgumentException('The selected farm does not belong to the authenticated organization.');
                }

                $data->setTenantId($organization->getId());
                $result = $this->persistProcessor->process($data, $operation, $uriVariables, $context);
                $this->entityManager->commit();
                return $result;
            } catch (\Throwable $exception) {
                $this->entityManager->rollback();
                throw $exception;
            }
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
