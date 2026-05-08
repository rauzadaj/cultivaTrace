<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\HarvestRecord;
use App\Entity\Plant;
use App\Entity\User;
use Doctrine\DBAL\LockMode;
use App\Enum\PlantStage;
use App\Enum\PlantStatus;
use App\Repository\PlantEventRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class HarvestWorkflowService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PlantEventRepository $eventRepository,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public function harvest(Plant $plant, User $user, array $payload): HarvestRecord
    {
        if (!$plant->isActive()) {
            throw new \InvalidArgumentException(sprintf(
                'Plant non archivable (statut actuel : %s)',
                $plant->getStatus()->value,
            ));
        }

        // Enforce forward-only stage rule: harvest is only allowed from FLOWERING
        if ($plant->getStage() !== PlantStage::FLOWERING) {
            throw new \InvalidArgumentException(sprintf(
                'La récolte n\'est possible que depuis le stade "%s" (stade actuel : "%s").',
                PlantStage::FLOWERING->value,
                $plant->getStage()->value,
            ));
        }

        $grossWeight = $payload['grossWeightG'] ?? null;
        $netWeight = $payload['netWeightG'] ?? null;
        $harvestedAt = (string) ($payload['harvestedAt'] ?? date('Y-m-d'));
        $notes = isset($payload['notes']) ? (string) $payload['notes'] : null;

        if ($grossWeight === null || $netWeight === null) {
            throw new \InvalidArgumentException('grossWeightG et netWeightG sont obligatoires');
        }

        if (!is_numeric($grossWeight) || (float) $grossWeight <= 0) {
            throw new \InvalidArgumentException('grossWeightG doit être un nombre strictement positif');
        }

        if (!is_numeric($netWeight) || (float) $netWeight <= 0) {
            throw new \InvalidArgumentException('netWeightG doit être un nombre strictement positif');
        }

        if ((float) $netWeight > (float) $grossWeight) {
            throw new \InvalidArgumentException('Le poids net ne peut pas être supérieur au poids brut');
        }

        $this->entityManager->beginTransaction();

        try {
            // Pessimistic write lock prevents double-harvest under concurrent requests
            $this->entityManager->lock($plant, LockMode::PESSIMISTIC_WRITE);

            // Re-check status inside the lock in case a concurrent request harvested first
            if (!$plant->isActive()) {
                throw new \InvalidArgumentException('Ce plant a déjà été récolté ou détruit.');
            }

            $harvest = new HarvestRecord();
            $harvest->setPlant($plant);
            $harvest->setTenantId($plant->getTenantId());
            $harvest->setGrossWeightG((string) $grossWeight);
            $harvest->setNetWeightG((string) $netWeight);
            $harvest->setHarvestedAt(new \DateTimeImmutable($harvestedAt));
            $harvest->setHarvestedBy($user);
            $harvest->setNotes($notes);
            $this->entityManager->persist($harvest);

            $plant->setStatus(PlantStatus::HARVESTED);
            $plant->setStage(PlantStage::HARVEST);

            $this->eventRepository->appendEvent(
                plant: $plant,
                eventType: 'harvest',
                user: $user,
                payload: [
                    'grossWeightG' => $grossWeight,
                    'netWeightG' => $netWeight,
                    'harvestedAt' => $harvestedAt,
                    'yieldRatio' => $harvest->getYieldRatio(),
                ],
                notes: $notes,
            );

            $this->entityManager->flush();
            $this->entityManager->commit();

            return $harvest;
        } catch (\Throwable $exception) {
            $this->entityManager->rollback();
            throw $exception;
        }
    }
}
