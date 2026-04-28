<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\LicenseDocument;
use App\Entity\Organization;
use App\Enum\LicenseStatus;
use App\Service\Storage\ArtifactStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class KybSubmissionService
{
    private const MAX_FILE_SIZE_BYTES = 10 * 1024 * 1024;

    /** @var array<string, string> */
    private const ALLOWED_MIME_TYPES = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private KybService $kybService,
        private ArtifactStorage $artifactStorage,
    ) {
    }

    public function submit(Organization $organization, string $licenseNumber, string $licenseType, ?UploadedFile $file): LicenseDocument
    {
        $filePath = null;

        if ($file instanceof UploadedFile) {
            $extension = $this->validateFile($file);
            $filePath = $this->artifactStorage->storeKybUpload($organization, $file, $extension);
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

    private function validateFile(UploadedFile $file): string
    {
        $path = $file->getPathname();
        if ('' === $path || !is_readable($path)) {
            throw new \InvalidArgumentException('Uploaded KYB file is unreadable.');
        }

        $size = $file->getSize();
        if (!is_int($size) || $size <= 0) {
            $size = filesize($path);
        }

        if (!is_int($size) || $size > self::MAX_FILE_SIZE_BYTES) {
            throw new \InvalidArgumentException('Uploaded KYB file exceeds the maximum allowed size.');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if (false === $finfo) {
            throw new \InvalidArgumentException('Unable to inspect uploaded KYB file.');
        }

        $mimeType = finfo_file($finfo, $path);
        finfo_close($finfo);

        if (!is_string($mimeType) || !isset(self::ALLOWED_MIME_TYPES[$mimeType])) {
            throw new \InvalidArgumentException('Uploaded KYB file type is not allowed.');
        }

        return self::ALLOWED_MIME_TYPES[$mimeType];
    }
}
