<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * LicenseDocument — license document uploaded by the operator during KYB.
 *
 * Workflow:
 *   1. POST /api/kyb/upload → upload file + create LicenseDocument (status: pending)
 *   2. Auto-verification via METRC/CTS API (KybService::verify())
 *   3. If API unavailable → manual validation by CultivaTrace admin
 *   4. Transition to active or rejected
 *
 * Dev storage: local (var/licenses/)
 * Prod storage: S3 (to be configured in milestone 5)
 */
#[ORM\Entity]
#[ORM\Table(name: 'license_document')]
#[ApiResource(operations: [
    new GetCollection(
        security: "is_granted('ROLE_SUPER_ADMIN') or is_granted('ROLE_ORG_ADMIN') or is_granted('ROLE_ORG_USER')"
    ),
    new Get(
        security: "(is_granted('ROLE_SUPER_ADMIN') or is_granted('ROLE_ORG_ADMIN') or is_granted('ROLE_ORG_USER')) and is_granted('TENANT_ACCESS', object)"
    ),
])]
class LicenseDocument
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private Uuid $id;

    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $tenantId;

    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Organization $organization;

    /**
     * Official license number entered by the operator
     * Ex: METRC-CO-12345, HC-LIC-CA-67890, BfArM-DE-001
     */
    #[ORM\Column(length: 255)]
    private string $licenseNumber;

    /**
     * License type by market:
     * metrc_usa | health_canada | bfarm_de | ansm_fr | ctls_dev (dev/test only)
     */
    #[ORM\Column(length: 50)]
    private string $licenseType;

    /**
     * Path of the uploaded file (local in dev, S3 key in prod)
     */
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $filePath = null;

    /**
     * pending | active | rejected | expired
     */
    #[ORM\Column(length: 50)]
    private string $status = 'pending';

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $licenseExpiresAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $submittedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $verifiedAt = null;

    /**
     * Verification method: auto_metrc | auto_cts | manual
     */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $verificationMethod = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $rejectionReason = null;

    public function __construct()
    {
        $this->id          = Uuid::v4();
        $this->submittedAt = new \DateTimeImmutable();
    }

    public function isActive(): bool { return $this->status === 'active'; }
    public function isPending(): bool { return $this->status === 'pending'; }
    public function isExpired(): bool
    {
        if ($this->licenseExpiresAt === null) return false;
        return $this->licenseExpiresAt < new \DateTimeImmutable();
    }

    public function getId(): Uuid { return $this->id; }
    public function getTenantId(): Uuid { return $this->tenantId; }
    public function setTenantId(Uuid $id): self { $this->tenantId = $id; return $this; }
    public function getOrganization(): Organization { return $this->organization; }
    public function setOrganization(Organization $org): self { $this->organization = $org; return $this; }
    public function getLicenseNumber(): string { return $this->licenseNumber; }
    public function setLicenseNumber(string $n): self { $this->licenseNumber = $n; return $this; }
    public function getLicenseType(): string { return $this->licenseType; }
    public function setLicenseType(string $t): self { $this->licenseType = $t; return $this; }
    public function getFilePath(): ?string { return $this->filePath; }
    public function setFilePath(?string $p): self { $this->filePath = $p; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $s): self { $this->status = $s; return $this; }
    public function getLicenseExpiresAt(): ?\DateTimeImmutable { return $this->licenseExpiresAt; }
    public function setLicenseExpiresAt(?\DateTimeImmutable $d): self { $this->licenseExpiresAt = $d; return $this; }
    public function getSubmittedAt(): \DateTimeImmutable { return $this->submittedAt; }
    public function getVerifiedAt(): ?\DateTimeImmutable { return $this->verifiedAt; }
    public function setVerifiedAt(?\DateTimeImmutable $d): self { $this->verifiedAt = $d; return $this; }
    public function getVerificationMethod(): ?string { return $this->verificationMethod; }
    public function setVerificationMethod(?string $m): self { $this->verificationMethod = $m; return $this; }
    public function getRejectionReason(): ?string { return $this->rejectionReason; }
    public function setRejectionReason(?string $r): self { $this->rejectionReason = $r; return $this; }
}
