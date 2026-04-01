<?php

namespace App\Controller;

use App\Service\PlanLimitsService;
use Doctrine\ORM\EntityManagerInterface;
use BackedEnum;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * GET /api/dashboard
 *
 * Endpoint agrégé pour le dashboard principal.
 * Retourne tous les KPIs en un seul appel API.
 */
#[Route('/api/dashboard', methods: ['GET'])]
class DashboardController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PlanLimitsService $planLimits,
    ) {}

    public function __invoke(#[CurrentUser] $user): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $org      = $user->getOrganization();
        $tenantId = (string) $org->getId();

        // Plants actifs par stade
        $plantsByStage = $this->em->createQuery(
            'SELECT p.stage, COUNT(p.id) as count
             FROM App\Entity\Plant p
             WHERE p.tenantId = :tenantId AND p.status = :status
             GROUP BY p.stage'
        )
        ->setParameter('tenantId', $org->getId(), 'uuid')
        ->setParameter('status', 'active')
        ->getResult();

        // Prochaines récoltes estimées (plants en floraison)
        $upcomingHarvests = $this->em->createQuery(
            'SELECT COUNT(p.id) as count
             FROM App\Entity\Plant p
             WHERE p.tenantId = :tenantId
               AND p.status = :status
               AND p.stage = :stage'
        )
        ->setParameter('tenantId', $org->getId(), 'uuid')
        ->setParameter('status', 'active')
        ->setParameter('stage', 'flowering')
        ->getSingleScalarResult();

        // Dernières récoltes (30j)
        $recentHarvests = $this->em->createQuery(
            'SELECT COUNT(h.id) as count, SUM(h.netWeightG) as totalGrams
             FROM App\Entity\HarvestRecord h
             INNER JOIN h.plant p
             WHERE p.tenantId = :tenantId
               AND h.harvestedAt >= :since'
        )
        ->setParameter('tenantId', $org->getId(), 'uuid')
        ->setParameter('since', new \DateTimeImmutable('-30 days'))
        ->getSingleResult();

        // Capteurs en alerte
        $sensorsInAlert = $this->em->createQuery(
            'SELECT COUNT(s.id) as count
             FROM App\Entity\Sensor s
             INNER JOIN s.room r
             INNER JOIN r.farm f
             WHERE f.organization = :org
               AND s.status = :status'
        )
        ->setParameter('org', $org)
        ->setParameter('status', 'warning')
        ->getSingleScalarResult();

        // Limites du plan
        $limits = $this->planLimits->getLimits($org);

        // Plants par stade en format lisible
        $stagesMap = [];
        foreach ($plantsByStage as $row) {
            $stage = $row['stage'];
            $stageKey = $stage instanceof BackedEnum
                ? $stage->value
                : (string) $stage;
            $stagesMap[$stageKey] = (int) $row['count'];
        }

        return $this->json([
            'organization' => [
                'name'          => $org->getName(),
                'plan'          => $org->getPlan()->value,
                'licenseStatus' => $org->getLicenseStatus()->value,
            ],
            'plants' => [
                'byStage'    => $stagesMap,
                'total'      => array_sum($stagesMap),
                'inFlowering'=> (int) $upcomingHarvests,
            ],
            'harvests' => [
                'last30Days'   => (int) ($recentHarvests['count'] ?? 0),
                'totalGrams'   => round((float) ($recentHarvests['totalGrams'] ?? 0), 1),
            ],
            'alerts' => [
                'sensorsInAlert' => (int) $sensorsInAlert,
            ],
            'limits' => $limits,
            'generatedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
    }
}
