<?php

namespace App\Controller;

use App\Entity\Plant;
use App\Service\HarvestWorkflowService;
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
        private readonly HarvestWorkflowService $harvestWorkflow,
    ) {}

    public function __invoke(Plant $plant, Request $request, #[CurrentUser] $user): JsonResponse
    {
        try {
            $harvest = $this->harvestWorkflow->harvest(
                $plant,
                $user,
                json_decode($request->getContent(), true) ?? [],
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
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
