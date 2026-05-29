<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\PlantEvent;
use App\Entity\User;
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
 * Aggregated endpoint for the main dashboard.
 * Returns all KPIs in a single API call.
 */
#[Route('/api/dashboard', methods: ['GET'])]
class DashboardController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PlanLimitsService $planLimits,
    ) {
    }

    public function __invoke(#[CurrentUser] ?User $user): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        if (!$user instanceof User || !$user->hasOrganization()) {
            throw $this->createAccessDeniedException('Authenticated user must belong to an organization.');
        }
        $org = $user->getOrganization();

        // Active plants by stage
        $plantsByStage = $this->em->createQuery(
            'SELECT p.stage, COUNT(p.id) as count
             FROM App\Entity\Plant p
             WHERE p.tenantId = :tenantId AND p.status = :status
             GROUP BY p.stage'
        )
        ->setParameter('tenantId', $org->getId(), 'uuid')
        ->setParameter('status', 'active')
        ->getResult();

        // Estimated upcoming harvests (flowering plants)
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

        // Recent harvests (last 30 days)
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

        // Sensors in alert
        $sensorsInAlert = $this->em->createQuery(
            'SELECT COUNT(s.id) as count
             FROM App\Entity\Sensor s
             INNER JOIN s.room r
             INNER JOIN r.farm f
             WHERE f.tenantId = :tenantId
               AND s.status = :status'
        )
        ->setParameter('tenantId', $org->getId(), 'uuid')
        ->setParameter('status', 'warning')
        ->getSingleScalarResult();

        $roomsCount = (int) $this->em->createQuery(
            'SELECT COUNT(r.id)
             FROM App\Entity\Room r
             WHERE r.tenantId = :tenantId'
        )
        ->setParameter('tenantId', $org->getId(), 'uuid')
        ->getSingleScalarResult();

        $roomAlerts = $this->em->createQuery(
            'SELECT r.id AS roomId, r.name AS roomName, r.capacityMax AS capacityMax, COUNT(p.id) AS activePlantCount
             FROM App\Entity\Room r
             LEFT JOIN r.plants p WITH p.status = :status
             WHERE r.tenantId = :tenantId
             GROUP BY r.id, r.name, r.capacityMax
             HAVING COUNT(p.id) >= r.capacityMax
             ORDER BY r.name ASC'
        )
        ->setParameter('tenantId', $org->getId(), 'uuid')
        ->setParameter('status', 'active')
        ->getResult();

        $spotlightPlants = $this->em->createQuery(
            'SELECT p.id AS id, p.rfidTag AS rfidTag, p.stage AS stage, p.status AS status, p.germinatedAt AS germinatedAt, s.name AS strainName, r.name AS roomName
             FROM App\Entity\Plant p
             LEFT JOIN p.strain s
             LEFT JOIN p.room r
             WHERE p.tenantId = :tenantId
               AND p.status = :status
             ORDER BY p.createdAt DESC'
        )
        ->setParameter('tenantId', $org->getId(), 'uuid')
        ->setParameter('status', 'active')
        ->setMaxResults(3)
        ->getResult();

        $recentEvents = $this->em->createQuery(
            'SELECT e.id AS id, e.eventType AS eventType, e.notes AS notes, e.occurredAt AS occurredAt, e.payload AS payload
             FROM App\Entity\PlantEvent e
             WHERE e.tenantId = :tenantId
             ORDER BY e.occurredAt DESC'
        )
        ->setParameter('tenantId', $org->getId(), 'uuid')
        ->setMaxResults(6)
        ->getResult();

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

        $roomAlertItems = array_map(static function (array $row): array {
            return [
                'id' => sprintf('room-capacity-%s', (string) $row['roomId']),
                'title' => (string) $row['roomName'],
                'message' => sprintf('%d/%d plants actifs', (int) $row['activePlantCount'], (int) $row['capacityMax']),
                'severity' => 'warning',
                'context' => 'Capacite maximale atteinte',
            ];
        }, $roomAlerts);

        $spotlightItems = array_map(static function (array $row): array {
            $id = (string) $row['id'];
            $rfidTag = isset($row['rfidTag']) ? trim((string) $row['rfidTag']) : '';
            $strainName = isset($row['strainName']) ? trim((string) $row['strainName']) : '';
            $germinatedAt = $row['germinatedAt'] instanceof \DateTimeInterface
                ? $row['germinatedAt']
                : new \DateTimeImmutable((string) $row['germinatedAt']);

            return [
                'id' => $id,
                'name' => '' !== $rfidTag ? $rfidTag : ('' !== $strainName ? sprintf('%s · %s', $strainName, substr($id, 0, 8)) : sprintf('Plant %s', substr($id, 0, 8))),
                'strain' => '' !== $strainName ? $strainName : 'Genetique non renseignee',
                'room' => isset($row['roomName']) ? (string) $row['roomName'] : 'Salle non renseignee',
                'stage' => self::normalizeValue($row['stage']),
                'status' => self::normalizeValue($row['status']),
                'ageInDays' => (int) $germinatedAt->diff(new \DateTimeImmutable())->days,
            ];
        }, $spotlightPlants);

        $recentEventItems = array_map(static function (array $row): array {
            return [
                'id' => (string) $row['id'],
                'eventType' => (string) $row['eventType'],
                'notes' => $row['notes'],
                'occurredAt' => $row['occurredAt'] instanceof \DateTimeInterface
                    ? $row['occurredAt']->format(\DateTimeInterface::ATOM)
                    : (string) $row['occurredAt'],
                'payload' => is_array($row['payload']) ? $row['payload'] : null,
            ];
        }, $recentEvents);

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
                'saturatedRooms' => count($roomAlertItems),
                'items' => $roomAlertItems,
            ],
            'rooms' => [
                'total' => $roomsCount,
            ],
            'overview' => [
                'spotlightPlants' => $spotlightItems,
                'recentEvents' => $recentEventItems,
            ],
            'limits' => $limits,
            'generatedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
    }

    private static function normalizeValue(mixed $value): string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return (string) $value;
    }
}
