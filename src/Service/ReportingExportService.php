<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Organization;
use App\Entity\ReportExport;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Sensiolabs\GotenbergBundle\GotenbergPdfInterface;
use Sensiolabs\GotenbergBundle\Processor\FileProcessor;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

final class ReportingExportService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly GotenbergPdfInterface $gotenberg,
        private readonly Filesystem $filesystem,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    /**
     * @param array{dateFrom: string, dateTo: string, farmId?: string|null, roomId?: string|null} $filters
     */
    public function createHarvestSummaryExport(Organization $organization, User $user, array $filters): ReportExport
    {
        $startDate = new \DateTimeImmutable($filters['dateFrom'] . ' 00:00:00');
        $endDate = new \DateTimeImmutable($filters['dateTo'] . ' 23:59:59');
        $farmId = $filters['farmId'] ?? null;
        $roomId = $filters['roomId'] ?? null;

        $dql = 'SELECT h.id AS harvestId, h.harvestedAt AS harvestedAt, h.grossWeightG AS grossWeightG, h.netWeightG AS netWeightG,
                       p.id AS plantId, p.rfidTag AS rfidTag, r.name AS roomName, f.name AS farmName, s.name AS strainName
                FROM App\Entity\HarvestRecord h
                INNER JOIN h.plant p
                INNER JOIN p.room r
                INNER JOIN r.farm f
                LEFT JOIN p.strain s
                WHERE p.tenantId = :tenantId
                  AND h.harvestedAt BETWEEN :startDate AND :endDate';

        if ($farmId !== null) {
            $dql .= ' AND f.id = :farmId';
        }

        if ($roomId !== null) {
            $dql .= ' AND r.id = :roomId';
        }

        $dql .= ' ORDER BY h.harvestedAt DESC';

        $query = $this->entityManager->createQuery($dql)
            ->setParameter('tenantId', $organization->getId(), 'uuid')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate);

        if ($farmId !== null) {
            $query->setParameter('farmId', $farmId, 'uuid');
        }

        if ($roomId !== null) {
            $query->setParameter('roomId', $roomId, 'uuid');
        }

        $rows = $query->getArrayResult();

        $summary = [
            'harvestCount' => count($rows),
            'grossWeightG' => round(array_sum(array_map(static fn (array $row): float => (float) $row['grossWeightG'], $rows)), 2),
            'netWeightG' => round(array_sum(array_map(static fn (array $row): float => (float) $row['netWeightG'], $rows)), 2),
        ];

        $fileName = sprintf('harvest-summary-%s-%s.pdf', $organization->getId(), (new \DateTimeImmutable())->format('YmdHis'));
        $relativePath = sprintf('var/report_exports/%s/%s', $organization->getId(), $fileName);
        $absolutePath = $this->projectDir . '/' . $relativePath;
        $this->ensureDirectory(\dirname($absolutePath));

        $this->renderPdfToPath(
            'pdf/harvest_summary.html.twig',
            [
                'organization' => $organization,
                'filters' => $filters,
                'summary' => $summary,
                'rows' => $rows,
                'generatedAt' => new \DateTimeImmutable(),
            ],
            $absolutePath,
        );

        return $this->persistExport($organization, $user, 'harvest_summary', 'pdf', $fileName, $relativePath, $filters, $summary);
    }

    /**
     * @param array{dateFrom: string, dateTo: string, format: string} $filters
     */
    public function createAuditExport(Organization $organization, User $user, array $filters): ReportExport
    {
        $startDate = new \DateTimeImmutable($filters['dateFrom'] . ' 00:00:00');
        $endDate = new \DateTimeImmutable($filters['dateTo'] . ' 23:59:59');
        $format = $filters['format'] === 'pdf' ? 'pdf' : 'csv';

        $rows = $this->entityManager->createQuery(
            'SELECT e.id AS eventId, e.eventType AS eventType, e.notes AS notes, e.occurredAt AS occurredAt,
                    p.id AS plantId, p.rfidTag AS rfidTag, u.email AS userEmail, r.name AS roomName
             FROM App\Entity\PlantEvent e
             INNER JOIN e.plant p
             INNER JOIN e.user u
             INNER JOIN p.room r
             WHERE e.tenantId = :tenantId
               AND e.occurredAt BETWEEN :startDate AND :endDate
             ORDER BY e.occurredAt DESC'
        )
            ->setParameter('tenantId', $organization->getId(), 'uuid')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getArrayResult();

        $summary = [
            'eventCount' => count($rows),
            'range' => ['dateFrom' => $filters['dateFrom'], 'dateTo' => $filters['dateTo']],
        ];

        $extension = $format === 'pdf' ? 'pdf' : 'csv';
        $fileName = sprintf('audit-export-%s-%s.%s', $organization->getId(), (new \DateTimeImmutable())->format('YmdHis'), $extension);
        $relativePath = sprintf('var/report_exports/%s/%s', $organization->getId(), $fileName);
        $absolutePath = $this->projectDir . '/' . $relativePath;
        $this->ensureDirectory(\dirname($absolutePath));

        if ($format === 'pdf') {
            $this->renderPdfToPath(
                'pdf/audit_export.html.twig',
                [
                    'organization' => $organization,
                    'filters' => $filters,
                    'summary' => $summary,
                    'rows' => $rows,
                    'generatedAt' => new \DateTimeImmutable(),
                ],
                $absolutePath,
            );
        } else {
            $this->writeCsv($absolutePath, $rows);
        }

        return $this->persistExport($organization, $user, 'audit_export', $format, $fileName, $relativePath, $filters, $summary);
    }

    private function writeCsv(string $absolutePath, array $rows): void
    {
        $handle = fopen($absolutePath, 'wb');
        if (!$handle) {
            throw new \RuntimeException(sprintf('Unable to open export file: %s', $absolutePath));
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['Event ID', 'Type', 'Plant', 'Room', 'User', 'Occurred At', 'Notes'], escape: '');
        foreach ($rows as $row) {
            fputcsv($handle, [
                substr((string) $row['eventId'], 0, 8),
                (string) $row['eventType'],
                trim((string) ($row['rfidTag'] ?? '')) !== '' ? (string) $row['rfidTag'] : substr((string) $row['plantId'], 0, 8),
                (string) $row['roomName'],
                (string) $row['userEmail'],
                $row['occurredAt'] instanceof \DateTimeInterface ? $row['occurredAt']->format(\DateTimeInterface::ATOM) : (string) $row['occurredAt'],
                (string) ($row['notes'] ?? ''),
            ], escape: '');
        }
        fclose($handle);
    }

    private function renderPdfToPath(string $template, array $context, string $absolutePath): void
    {
        $directory = \dirname($absolutePath);
        $fileName = pathinfo($absolutePath, \PATHINFO_FILENAME);

        $this->gotenberg
            ->html()
            ->content($template, $context)
            ->fileName($fileName)
            ->generate()
            ->processor(new FileProcessor($this->filesystem, $directory))
            ->process();
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<string, mixed> $summary
     */
    private function persistExport(
        Organization $organization,
        User $user,
        string $type,
        string $format,
        string $fileName,
        string $relativePath,
        array $filters,
        array $summary,
    ): ReportExport {
        $export = new ReportExport();
        $export
            ->setTenantId($organization->getId())
            ->setType($type)
            ->setFormat($format)
            ->setStatus('ready')
            ->setFileName($fileName)
            ->setFilePath($relativePath)
            ->setFilters($filters)
            ->setSummary($summary)
            ->setGeneratedBy($user);

        $this->entityManager->persist($export);
        $this->entityManager->flush();

        return $export;
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Unable to create report export directory: %s', $directory));
        }
    }
}
