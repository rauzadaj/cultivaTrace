<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Plant;
use App\Entity\User;
use App\Repository\PlantEventRepository;
use App\Service\License\LicenseGuard;
use App\Service\PlanLimitExceededException;
use App\Service\PlanLimitsService;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * @implements ProcessorInterface<Plant, Plant|null>
 */
final class PlantStateProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<Plant, Plant|null> $persistProcessor
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly PlantEventRepository $plantEventRepository,
        private readonly LicenseGuard $licenseGuard,
        private readonly PlanLimitsService $planLimits,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $isCreate = false;
        $previousStage = null;
        $previousRoomId = null;
        $user = null;

        if ($data instanceof Plant) {
            $token = $this->tokenStorage->getToken();
            $user = $token?->getUser();

            if (!$user instanceof User) {
                throw new InvalidArgumentException('An authenticated user is required to create or update a plant.');
            }

            if (!$user->hasOrganization()) {
                throw new InvalidArgumentException('The authenticated user must belong to an organization.');
            }

            $organization = $user->getOrganization();

            $data->setTenantId($organization->getId());

            if (!isset($context['previous_data']) || !$context['previous_data'] instanceof Plant) {
                $isCreate = true;
                $this->licenseGuard->assertLicenseApproved($organization);
                try {
                    $this->planLimits->checkPlantLimit($organization);
                } catch (PlanLimitExceededException $exception) {
                    throw new HttpException(
                        402,
                        json_encode($exception->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: $exception->getMessage(),
                        $exception,
                    );
                }
                $data->setCreatedBy($user);
                // Force germination stage on creation — clients cannot skip ahead
                $data->setStage(\App\Enum\PlantStage::GERMINATION);
            } else {
                /** @var Plant $previousPlant */
                $previousPlant = $context['previous_data'];
                $previousStage = $previousPlant->getStage();
                $previousRoomId = (string) $previousPlant->getRoom()->getId();

                // Enforce forward-only stage transitions (regulatory requirement)
                if ($data->getStage() !== $previousStage) {
                    $allowed = $previousStage->next();
                    if ($allowed === null || $data->getStage() !== $allowed) {
                        throw new HttpException(
                            422,
                            sprintf(
                                'Invalid stage transition: "%s" → "%s". Only transition to "%s" is allowed.',
                                $previousStage->value,
                                $data->getStage()->value,
                                $allowed?->value ?? 'none (terminal stage)',
                            )
                        );
                    }
                }
            }
        }

        $this->entityManager->beginTransaction();
        try {
            $result = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

            if ($result instanceof Plant && $user instanceof User) {
                if ($isCreate) {
                    $this->plantEventRepository->appendEvent(
                        $result,
                        'germination',
                        $user,
                        [
                            'to' => $result->getStage()->value,
                            'roomId' => (string) $result->getRoom()->getId(),
                        ],
                        sprintf('Plant %s created in %s.', $result->getRfidTag() ?? (string) $result->getId(), $result->getRoom()->getName()),
                    );
                } else {
                    if ($previousStage !== null && $previousStage !== $result->getStage()) {
                        $this->plantEventRepository->appendEvent(
                            $result,
                            'stage_change',
                            $user,
                            [
                                'from' => $previousStage->value,
                                'to' => $result->getStage()->value,
                            ],
                            sprintf('Transition from %s to %s.', $previousStage->value, $result->getStage()->value),
                        );
                    }

                    if ($previousRoomId !== null && $previousRoomId !== (string) $result->getRoom()->getId()) {
                        $this->plantEventRepository->appendEvent(
                            $result,
                            'room_move',
                            $user,
                            [
                                'fromRoomId' => $previousRoomId,
                                'toRoomId' => (string) $result->getRoom()->getId(),
                            ],
                            sprintf('Moved to room %s.', $result->getRoom()->getName()),
                        );
                    }
                }
            }

            $this->entityManager->commit();
        } catch (\Throwable $e) {
            $this->entityManager->rollback();
            throw $e;
        }

        return $result;
    }
}
