<?php

namespace App\Service;

use App\Entity\DestructionIntent;
use App\Entity\Plant;
use App\Entity\User;
use App\Enum\PlantStatus;
use App\Repository\PlantEventRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DestructionWorkflowService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PlantEventRepository $eventRepository,
    ) {
    }

    public function declareIntent(Plant $plant, User $user, string $reason): DestructionIntent
    {
        if (!$plant->isActive()) {
            throw new \InvalidArgumentException(sprintf(
                'Plant non destructible (statut : %s)',
                $plant->getStatus()->value,
            ));
        }

        if (trim($reason) === '') {
            throw new \InvalidArgumentException('La raison est obligatoire');
        }

        $intent = new DestructionIntent();
        $intent->setPlant($plant);
        $intent->setTenantId($plant->getTenantId());
        $intent->setReason($reason);
        $intent->setDeclaredBy($user);
        $this->entityManager->persist($intent);

        $this->eventRepository->appendEvent(
            plant: $plant,
            eventType: 'destruction_intent',
            user: $user,
            payload: [
                'intentId' => (string) $intent->getId(),
                'reason' => $reason,
                'legalDateMin' => $intent->getLegalDateMin()->format('Y-m-d'),
            ],
        );

        $this->entityManager->flush();

        return $intent;
    }

    /** @param array<string, mixed> $payload */
    public function confirmIntent(DestructionIntent $intent, User $user, array $payload): void
    {
        if (!$intent->canBeConfirmed()) {
            throw new \InvalidArgumentException(sprintf(
                'Destruction impossible avant le %s (%d jour(s) restant(s))',
                $intent->getLegalDateMin()->format('d/m/Y'),
                $intent->getDaysRemaining(),
            ));
        }

        $totalWeight = $payload['totalWeightG'] ?? null;
        $ratio = $payload['nonCannabisRatio'] ?? null;
        $photoUrls = $payload['photoUrls'] ?? [];

        if (!$totalWeight || $ratio === null) {
            throw new \InvalidArgumentException('totalWeightG et nonCannabisRatio sont obligatoires');
        }

        if ((float) $ratio < 0.50) {
            throw new \InvalidArgumentException(sprintf(
                'Le ratio matières non-cannabis doit être >= 50%% (reçu : %.1f%%)',
                (float) $ratio * 100,
            ));
        }

        if (!is_array($photoUrls) || $photoUrls === []) {
            throw new \InvalidArgumentException('Au moins une photo est obligatoire');
        }

        $this->entityManager->beginTransaction();

        try {
            $intent->setStatus('confirmed');
            $intent->setTotalWeightG((string) $totalWeight);
            $intent->setNonCannabisRatio((string) $ratio);
            $intent->setPhotoUrls($photoUrls);
            $intent->setConfirmedAt(new \DateTimeImmutable());
            $intent->setConfirmedBy($user);

            $plant = $intent->getPlant();
            $plant->setStatus(PlantStatus::DESTROYED);

            $this->eventRepository->appendEvent(
                plant: $plant,
                eventType: 'destruction_confirmed',
                user: $user,
                payload: [
                    'intentId' => (string) $intent->getId(),
                    'totalWeightG' => $totalWeight,
                    'nonCannabisRatio' => $ratio,
                    'photoUrls' => $photoUrls,
                ],
            );

            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (\Throwable $exception) {
            $this->entityManager->rollback();
            throw $exception;
        }
    }
}
