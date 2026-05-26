<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Plant;
use App\Entity\User;
use App\Service\HarvestWorkflowService;
use App\Service\License\LicenseGuard;
use App\Security\Voter\PlantVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * POST /api/plants/{id}/harvest
 *
 * Atomic transaction:
 *   1. Creates HarvestRecord
 *   2. Updates Plant (status + stage)
 *   3. Adds PlantEvent "harvest" to the audit trail
 *
 * If any of the 3 operations fails → full rollback.
 */
#[Route('/api/plants/{id}/harvest', methods: ['POST'])]
class HarvestController extends AbstractController
{
    public function __construct(
        private readonly HarvestWorkflowService $harvestWorkflow,
        private readonly LicenseGuard $licenseGuard,
    ) {
    }

    public function __invoke(Plant $plant, Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        $this->denyAccessUnlessGranted(PlantVoter::HARVEST, $plant);

        if (!$user instanceof User || !$user->hasOrganization()) {
            throw $this->createAccessDeniedException('Authenticated user required.');
        }

        $this->licenseGuard->assertLicenseApproved($user->getOrganization());

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
