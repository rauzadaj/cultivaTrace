<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\LicenseDocument;
use App\Entity\Organization;
use App\Enum\LicenseStatus;
use App\Service\KybService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class KybServiceTest extends TestCase
{
    public function testManualApprovalSetsApprovedStatuses(): void
    {
        $service = $this->createService();
        [$organization, $license] = $this->createLicenseFixture();

        $service->applyVerificationResult($license, [
            'verified' => true,
            'method' => 'manual',
            'reviewed' => true,
            'expiresAt' => '2030-01-01',
            'reason' => null,
        ]);

        self::assertSame('active', $license->getStatus());
        self::assertSame(LicenseStatus::ACTIVE, $organization->getLicenseStatus());
        self::assertSame('manual', $license->getVerificationMethod());
        self::assertNull($license->getRejectionReason());
        self::assertNotNull($license->getVerifiedAt());
    }

    public function testManualRejectionSetsRejectedStatuses(): void
    {
        $service = $this->createService();
        [$organization, $license] = $this->createLicenseFixture();

        $service->applyVerificationResult($license, [
            'verified' => false,
            'method' => 'manual',
            'reviewed' => true,
            'reason' => 'Licence invalide',
        ]);

        self::assertSame('rejected', $license->getStatus());
        self::assertSame(LicenseStatus::REJECTED, $organization->getLicenseStatus());
        self::assertSame('manual', $license->getVerificationMethod());
        self::assertSame('Licence invalide', $license->getRejectionReason());
    }

    public function testRejectedLicenseCannotReturnToPendingWithoutNewSubmission(): void
    {
        $service = $this->createService(expectedFlushes: 2);
        [$organization, $license] = $this->createLicenseFixture();

        $service->applyVerificationResult($license, [
            'verified' => false,
            'method' => 'manual',
            'reviewed' => true,
            'reason' => 'Document refuse',
        ]);

        $service->applyVerificationResult($license, [
            'verified' => false,
            'method' => 'manual',
            'reviewed' => false,
            'reason' => 'Nouvelle revue requise',
        ]);

        self::assertSame('rejected', $license->getStatus());
        self::assertSame(LicenseStatus::REJECTED, $organization->getLicenseStatus());
    }

    private function createService(int $expectedFlushes = 1): KybService
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly($expectedFlushes))->method('flush');

        return new KybService(
            $entityManager,
            $this->createMock(MailerInterface::class),
            $this->createMock(HttpClientInterface::class),
            $this->createMock(LoggerInterface::class),
        );
    }

    /**
     * @return array{0: Organization, 1: LicenseDocument}
     */
    private function createLicenseFixture(): array
    {
        $organization = (new Organization())
            ->setName('Org KYB')
            ->setCountry('FR')
            ->setLicenseStatus(LicenseStatus::PENDING);

        $license = (new LicenseDocument())
            ->setOrganization($organization)
            ->setTenantId($organization->getId())
            ->setLicenseNumber('LIC-001')
            ->setLicenseType('health_canada')
            ->setStatus(LicenseStatus::PENDING->value);

        return [$organization, $license];
    }
}
