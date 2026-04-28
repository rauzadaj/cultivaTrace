<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ReportExport;
use App\Entity\User;
use App\Security\Voter\TenantAwareVoter;
use App\Service\License\LicenseGuard;
use App\Service\ReportingExportService;
use App\Service\Storage\ArtifactStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class ReportingController extends AbstractController
{
    public function __construct(
        private readonly ReportingExportService $reportingExportService,
        private readonly EntityManagerInterface $entityManager,
        private readonly LicenseGuard $licenseGuard,
        private readonly ArtifactStorage $artifactStorage,
    ) {
    }

    #[Route('/api/reporting/harvest-summary', methods: ['POST'])]
    public function createHarvestSummary(Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        $organization = $this->assertAdminUser($user);
        $this->licenseGuard->assertLicenseApproved($organization);
        $payload = json_decode($request->getContent(), true) ?? [];

        if (!$this->isValidDateRange($payload['dateFrom'] ?? null, $payload['dateTo'] ?? null)) {
            return $this->json(['error' => 'dateFrom et dateTo sont obligatoires au format YYYY-MM-DD.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $export = $this->reportingExportService->createHarvestSummaryExport($organization, $user, [
            'dateFrom' => (string) $payload['dateFrom'],
            'dateTo' => (string) $payload['dateTo'],
            'farmId' => isset($payload['farmId']) ? (string) $payload['farmId'] : null,
            'roomId' => isset($payload['roomId']) ? (string) $payload['roomId'] : null,
        ]);

        return $this->json($this->normalizeExport($export), Response::HTTP_CREATED);
    }

    #[Route('/api/reporting/audit-export', methods: ['POST'])]
    public function createAuditExport(Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        $organization = $this->assertAdminUser($user);
        $this->licenseGuard->assertLicenseApproved($organization);
        $payload = json_decode($request->getContent(), true) ?? [];

        if (!$this->isValidDateRange($payload['dateFrom'] ?? null, $payload['dateTo'] ?? null)) {
            return $this->json(['error' => 'dateFrom et dateTo sont obligatoires au format YYYY-MM-DD.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $export = $this->reportingExportService->createAuditExport($organization, $user, [
            'dateFrom' => (string) $payload['dateFrom'],
            'dateTo' => (string) $payload['dateTo'],
            'format' => \in_array(($payload['format'] ?? 'csv'), ['csv', 'pdf'], true) ? (string) $payload['format'] : 'csv',
        ]);

        return $this->json($this->normalizeExport($export), Response::HTTP_CREATED);
    }

    #[Route('/api/reporting/exports/{id}/download', methods: ['GET'])]
    public function download(ReportExport $export, #[CurrentUser] ?User $user): BinaryFileResponse
    {
        $this->assertAdminUser($user);

        if (!$this->isGranted(TenantAwareVoter::ACCESS, $export)) {
            throw $this->createAccessDeniedException('Cross-tenant report access is forbidden.');
        }

        $absolutePath = $this->artifactStorage->resolveReportPath($export->getFilePath());
        if (!is_file($absolutePath)) {
            throw $this->createNotFoundException('Report file not found.');
        }

        return $this->file($absolutePath, $export->getFileName());
    }

    private function assertAdminUser(?User $user): \App\Entity\Organization
    {
        $this->denyAccessUnlessGranted('ROLE_ORG_ADMIN');

        if (!$user instanceof User || $user->getOrganization() === null) {
            throw $this->createAccessDeniedException('Authenticated organization admin required.');
        }

        return $user->getOrganization();
    }

    private function isValidDateRange(mixed $dateFrom, mixed $dateTo): bool
    {
        return \is_string($dateFrom)
            && \is_string($dateTo)
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) === 1
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) === 1;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeExport(ReportExport $export): array
    {
        return [
            'id' => (string) $export->getId(),
            'type' => $export->getType(),
            'format' => $export->getFormat(),
            'status' => $export->getStatus(),
            'fileName' => $export->getFileName(),
            'filters' => $export->getFilters(),
            'summary' => $export->getSummary(),
            'createdAt' => $export->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'downloadUrl' => sprintf('/api/reporting/exports/%s/download', $export->getId()),
        ];
    }
}
