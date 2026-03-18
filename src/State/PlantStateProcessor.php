<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Plant;
use App\Entity\User;
use App\Repository\PlantEventRepository;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class PlantStateProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly PlantEventRepository $plantEventRepository,
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

            $organization = $user->getOrganization();
            if ($organization === null) {
                throw new InvalidArgumentException('The authenticated user must belong to an organization.');
            }

            $data->setTenantId($organization->getId());

            if (!isset($context['previous_data']) || !$context['previous_data'] instanceof Plant) {
                $isCreate = true;
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
