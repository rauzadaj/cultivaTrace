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
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class PlantStateProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly PlantEventRepository $plantEventRepository,
        private readonly LicenseGuard $licenseGuard,
        private readonly PlanLimitsService $planLimits,
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

            $this->licenseGuard->assertLicenseApproved($organization);

            $data->setTenantId($organization->getId());

            if (!isset($context['previous_data']) || !$context['previous_data'] instanceof Plant) {
                $isCreate = true;
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
            } else {
                /** @var Plant $previousPlant */
                $previousPlant = $context['previous_data'];
                $previousStage = $previousPlant->getStage();
                $previousRoomId = (string) $previousPlant->getRoom()->getId();
            }
        }

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
                    sprintf('Plant %s cree dans %s.', $result->getRfidTag() ?? (string) $result->getId(), $result->getRoom()->getName()),
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
                        sprintf('Transition de %s vers %s.', $previousStage->value, $result->getStage()->value),
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
                        sprintf('Deplacement vers la salle %s.', $result->getRoom()->getName()),
                    );
                }
            }
        }

        return $result;
    }
}
