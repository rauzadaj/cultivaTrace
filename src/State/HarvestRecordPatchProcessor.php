<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\HarvestRecord;
use App\Entity\User;
use App\Repository\PlantEventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Appends a hash-chained harvest_correction PlantEvent whenever a HarvestRecord
 * is patched via the API, keeping the append-only audit trail consistent with
 * the corrected weights stored in HarvestRecord.
 */
/**
 * @implements ProcessorInterface<HarvestRecord, HarvestRecord|null>
 */
final class HarvestRecordPatchProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<HarvestRecord, HarvestRecord|null> $persistProcessor
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly PlantEventRepository $eventRepository,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly EntityManagerInterface $entityManager,
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

        $user = $this->tokenStorage->getToken()?->getUser();

        $this->entityManager->beginTransaction();
        try {
            $result = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

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

            $this->entityManager->commit();
        } catch (\Throwable $e) {
            $this->entityManager->rollback();
            throw $e;
        }

        return $result;
    }
}
