<?php

namespace App\Service;

use App\Entity\LicenseDocument;
use App\Entity\Organization;
use App\Enum\LicenseStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class KybSubmissionService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private KybService $kybService,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
    }

    public function submit(Organization $organization, string $licenseNumber, string $licenseType, ?UploadedFile $file): LicenseDocument
    {
        $filePath = null;

        if ($file instanceof UploadedFile) {
            $uploadDir = $this->projectDir . '/var/licenses/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $filename = sprintf(
                '%s_%s.%s',
                $organization->getId(),
                (new \DateTimeImmutable())->format('Ymd_His'),
                $file->guessExtension() ?? 'pdf',
            );

            $file->move($uploadDir, $filename);
            $filePath = 'var/licenses/' . $filename;
        }

        $license = new LicenseDocument();
        $license->setTenantId($organization->getId());
        $license->setOrganization($organization);
        $license->setLicenseNumber($licenseNumber);
        $license->setLicenseType($licenseType);
        $license->setFilePath($filePath);
        $license->setStatus('pending');

        $this->entityManager->persist($license);
        $organization->setLicenseStatus(LicenseStatus::PENDING);
        $this->entityManager->flush();

        $result = $this->kybService->verify($license);
        $this->kybService->applyVerificationResult($license, $result);

        return $license;
    }
}
