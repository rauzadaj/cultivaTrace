<?php

namespace App\Controller;

use App\Entity\Plant;
use App\Entity\HarvestRecord;
use App\Enum\PlantStatus;
use App\Enum\PlantStage;
use App\Repository\PlantEventRepository;
use App\Security\Voter\PlantVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * POST /api/plants/{id}/harvest
 *
 * Transaction atomique :
 *   1. Crée HarvestRecord
 *   2. Met à jour Plant (status + stage)
 *   3. Ajoute PlantEvent "harvest" dans l'audit trail
 *
 * Si l'une des 3 opérations échoue → rollback complet.
 */
#[Route('/api/plants/{id}/harvest', methods: ['POST'])]
class HarvestController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PlantEventRepository $eventRepo,
    ) {}

    public function __invoke(Plant $plant, Request $request, #[CurrentUser] $user): JsonResponse
    {
        $this->denyAccessUnlessGranted(PlantVoter::HARVEST, $plant);

        if (!$plant->isActive()) {
            return $this->json([
                'error' => sprintf('Plant non archivable (statut actuel : %s)', $plant->getStatus()->value),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data        = json_decode($request->getContent(), true) ?? [];
        $grossWeight = $data['grossWeightG'] ?? null;
        $netWeight   = $data['netWeightG'] ?? null;
        $harvestedAt = $data['harvestedAt'] ?? date('Y-m-d');
        $notes       = $data['notes'] ?? null;

        if (!$grossWeight || !$netWeight) {
            return $this->json(['error' => 'grossWeightG et netWeightG sont obligatoires'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ((float) $netWeight > (float) $grossWeight) {
            return $this->json(['error' => 'Le poids net ne peut pas être supérieur au poids brut'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->beginTransaction();
        try {
            $harvest = new HarvestRecord();
            $harvest->setPlant($plant);
            $harvest->setTenantId($plant->getTenantId());
            $harvest->setGrossWeightG((string) $grossWeight);
            $harvest->setNetWeightG((string) $netWeight);
            $harvest->setHarvestedAt(new \DateTimeImmutable($harvestedAt));
            $harvest->setHarvestedBy($user);
            $harvest->setNotes($notes);
            $this->em->persist($harvest);

            $plant->setStatus(PlantStatus::HARVESTED);
            $plant->setStage(PlantStage::HARVEST);

            $this->eventRepo->appendEvent(
                plant: $plant,
                eventType: 'harvest',
                user: $user,
                payload: [
                    'grossWeightG' => $grossWeight,
                    'netWeightG'   => $netWeight,
                    'harvestedAt'  => $harvestedAt,
                    'yieldRatio'   => $harvest->getYieldRatio(),
                ],
                notes: $notes,
            );

            $this->em->flush();
            $this->em->commit();

        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        }

        return $this->json([
            'id'           => (string) $harvest->getId(),
            'grossWeightG' => $harvest->getGrossWeightG(),
            'netWeightG'   => $harvest->getNetWeightG(),
            'yieldRatio'   => $harvest->getYieldRatio(),
            'harvestedAt'  => $harvest->getHarvestedAt()->format('Y-m-d'),
        ], Response::HTTP_CREATED);
    }
}
