<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\Export\SpreadsheetCell;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * GET /api/compliance/ctsreport?month=YYYY-MM
 *
 * Rapport mensuel Health Canada CTS (Cannabis Tracking System).
 *
 * ⚠️ SYNC-03 : le format CSV doit être validé par un vrai client canadien
 * avec son contact Health Canada avant utilisation en production.
 *
 * La spec officielle est disponible sur :
 * https://www.canada.ca/en/health-canada/services/drugs-medication/cannabis/
 *    licensed-producers/cannabis-tracking-system.html
 */
#[Route('/api/compliance/ctsreport', methods: ['GET'])]
class CTSReportController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    public function __invoke(Request $request, #[CurrentUser] ?User $user): Response
    {
        if (!$user instanceof User || !$user->hasOrganization()) {
            throw $this->createAccessDeniedException('An authenticated organization user is required.');
        }

        $month = $request->query->get('month', date('Y-m'));

        if (!preg_match('/^(?!0000)\d{4}-(0[1-9]|1[0-2])$/D', $month)) {
            return $this->json(
                ['error' => 'Format invalide. Utilisez YYYY-MM'],
                Response::HTTP_BAD_REQUEST
            );
        }

        [$year, $monthNum] = explode('-', $month);
        $startDate = new \DateTimeImmutable("{$year}-{$monthNum}-01 00:00:00");
        $endDate   = $startDate->modify('last day of this month')->setTime(23, 59, 59);
        $tenantId  = $user->getOrganization()->getId();

        $plants = $this->fetchPlantsForPeriod($tenantId, $startDate, $endDate);

        $response = new StreamedResponse(function () use ($plants) {
            $handle = fopen('php://output', 'w');

            // BOM UTF-8 pour Excel
            fwrite($handle, "\xEF\xBB\xBF");

            // En-têtes CTS Health Canada
            fputcsv($handle, [
                'Report Period',
                'Plant ID',
                'Strain',
                'Cannabis Type',
                'Stage',
                'Status',
                'Germinated At',
                'Room',
                'Harvested At',
                'Gross Weight (g)',
                'Net Weight (g)',
                'Destroyed At',
                'Destruction Reason',
                'Quarantined',
                'Notes',
            ], escape: '');

            foreach ($plants as $row) {
                fputcsv($handle, $row, escape: '');
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="cts-report-%s.csv"', $month));

        return $response;
    }

    private function fetchPlantsForPeriod(\Symfony\Component\Uid\Uuid $tenantId, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $period = $start->format('Y-m');

        $plants = $this->em->createQuery(
            'SELECT p, r, s, h
             FROM App\Entity\Plant p
             LEFT JOIN p.room r
             LEFT JOIN p.strain s
             LEFT JOIN p.harvestRecord h
             WHERE p.tenantId = :tenantId
               AND p.germinatedAt <= :endDate
             ORDER BY p.germinatedAt ASC'
        )
        ->setParameter('tenantId', $tenantId, UuidType::NAME)
        ->setParameter('endDate', $end)
        ->getResult();

        $rows = [];
        foreach ($plants as $plant) {
            $harvest = $plant->getHarvestRecord();
            $rows[]  = [
                $period,
                substr((string) $plant->getId(), 0, 8),
                SpreadsheetCell::text($plant->getStrain()?->getName() ?? 'N/A'),
                SpreadsheetCell::text($plant->getStrain()?->getCannabisType() ?? 'marijuana'),
                $plant->getStage()->value,
                $plant->getStatus()->value,
                $plant->getGerminatedAt()->format('Y-m-d'),
                SpreadsheetCell::text($plant->getRoom()->getName()),
                $harvest?->getHarvestedAt()->format('Y-m-d') ?? '',
                $harvest?->getGrossWeightG() ?? '',
                $harvest?->getNetWeightG() ?? '',
                '', // destroyed_at — populated via DestructionIntent
                '',
                'no', // quarantined — à enrichir avec InputRecord::isQuarantined()
                '',
            ];
        }

        return $rows;
    }
}
