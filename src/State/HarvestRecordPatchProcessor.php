<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\HarvestRecord;
use App\Entity\User;
use App\Repository\PlantEventRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Appends a hash-chained harvest_correction PlantEvent whenever a HarvestRecord
 * is patched via the API, keeping the append-only audit trail consistent with
 * the corrected weights stored in HarvestRecord.
 */
final class HarvestRecordPatchProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly PlantEventRepository $eventRepository,
        private readonly TokenStorageInterface $tokenStorage,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof HarvestRecord) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        $previous = $context['previous_data'] ?? null;
        $before = $previous instanceof HarvestRecord ? [
            'grossWeightG' => $previous->getGrossWeightG(),
            'netWeightG'   => $previous->getNetWeightG(),
            'notes'        => $previous->getNotes(),
        ] : null;

        $result = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        $user = $this->tokenStorage->getToken()?->getUser();

        $this->eventRepository->appendEvent(
            plant: $data->getPlant(),
            eventType: 'harvest_correction',
            user: $user instanceof User ? $user : null,
            payload: [
                'before' => $before,
                'after'  => [
                    'grossWeightG' => $data->getGrossWeightG(),
                    'netWeightG'   => $data->getNetWeightG(),
                    'notes'        => $data->getNotes(),
                ],
            ],
        );

        return $result;
    }
}
