<?php

declare(strict_types=1);

namespace App\Service\Storage;

use App\Entity\Organization;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class ArtifactStorage
{
    private const KYB_PREFIX = 'licenses';
    private const REPORT_PREFIX = 'report_exports';

    public function __construct(
        private Filesystem $filesystem,
        private string $projectDir,
        private string $kybStorageRoot,
        private string $reportStorageRoot,
    ) {
    }

    public function storeKybUpload(Organization $organization, UploadedFile $file, string $extension): string
    {
        $fileName = sprintf(
            '%s_%s.%s',
            $organization->getId(),
            (new \DateTimeImmutable())->format('Ymd_His'),
            $extension,
        );

        $storagePath = sprintf('%s/%s/%s', self::KYB_PREFIX, $organization->getId(), $fileName);
        $absolutePath = $this->resolvePath($storagePath, self::KYB_PREFIX, $this->kybStorageRoot);
        $directory = \dirname($absolutePath);

        $this->filesystem->mkdir($directory, 0755);
        $file->move($directory, basename($absolutePath));

        return $storagePath;
    }

    /**
     * @return array{storagePath: string, absolutePath: string}
     */
    public function createReportPath(Organization $organization, string $fileName): array
    {
        $storagePath = sprintf('%s/%s/%s', self::REPORT_PREFIX, $organization->getId(), $fileName);
        $absolutePath = $this->resolvePath($storagePath, self::REPORT_PREFIX, $this->reportStorageRoot);

        $this->filesystem->mkdir(\dirname($absolutePath), 0775);

        return [
            'storagePath' => $storagePath,
            'absolutePath' => $absolutePath,
        ];
    }

    public function resolveReportPath(string $storagePath): string
    {
        return $this->resolvePath($storagePath, self::REPORT_PREFIX, $this->reportStorageRoot);
    }

    private function resolvePath(string $storagePath, string $prefix, string $root): string
    {
        $normalizedPath = ltrim(str_replace('\\', '/', $storagePath), '/');
        $legacyPrefix = 'var/' . $prefix . '/';
        $currentPrefix = $prefix . '/';
        $isLegacy = str_starts_with($normalizedPath, $legacyPrefix);

        if ($isLegacy) {
            $resolved = $this->projectDir . '/' . $normalizedPath;
            // Legacy paths are anchored to the project root, not the storage root
            $checkRoot = $this->projectDir;
        } else {
            $relativePath = str_starts_with($normalizedPath, $currentPrefix)
                ? substr($normalizedPath, strlen($currentPrefix))
                : $normalizedPath;

            $resolved = rtrim(str_replace('\\', '/', $root), '/') . '/' . ltrim($relativePath, '/');
            $checkRoot = $root;
        }

        // Prevent path traversal: resolved path must stay within its check root
        $rootReal = realpath($checkRoot) ?: rtrim(str_replace('\\', '/', $checkRoot), '/');
        $resolvedDir = realpath(\dirname($resolved));

        if ($resolvedDir === false) {
            // Directory does not exist yet — verify the path without resolving symlinks
            $resolvedNormalized = str_replace('\\', '/', $resolved);
            if (!str_starts_with($resolvedNormalized, rtrim(str_replace('\\', '/', $rootReal), '/'))) {
                throw new \InvalidArgumentException(sprintf('Path traversal detected for storage path: %s', $storagePath));
            }
        } elseif (!str_starts_with($resolvedDir, $rootReal)) {
            throw new \InvalidArgumentException(sprintf('Path traversal detected for storage path: %s', $storagePath));
        }

        return $resolved;
    }
}
