<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Local cache of Health Canada's authorized licensed producers list.
 * Populated daily by SyncHealthCanadaRegistryCommand.
 *
 * Source: https://health-products.canada.ca/api/dataset/
 *         95c29d8f-3688-4a37-aca0-1a50af7b1c86
 */
#[ORM\Entity]
#[ORM\Table(name: 'health_canada_registry')]
#[ORM\UniqueConstraint(name: 'uniq_hc_license_number', columns: ['license_number'])]
#[ORM\Index(name: 'idx_hc_status', columns: ['status'])]
class HealthCanadaLicensedProducer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(length: 64, unique: true)]
    private string $licenseNumber;

    #[ORM\Column(length: 255)]
    private string $companyName;

    #[ORM\Column(length: 128)]
    private string $licenseType = '';

    /** active | suspended | expired | cancelled */
    #[ORM\Column(length: 32)]
    private string $status = 'active';

    #[ORM\Column(length: 64)]
    private string $province = '';

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $issuedAt = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $syncedAt;

    public function __construct()
    {
        $this->syncedAt = new \DateTimeImmutable();
    }

    public function getId(): int { return $this->id; }
    public function getLicenseNumber(): string { return $this->licenseNumber; }
    public function setLicenseNumber(string $v): self { $this->licenseNumber = strtoupper(trim($v)); return $this; }
    public function getCompanyName(): string { return $this->companyName; }
    public function setCompanyName(string $v): self { $this->companyName = trim($v); return $this; }
    public function getLicenseType(): string { return $this->licenseType; }
    public function setLicenseType(string $v): self { $this->licenseType = trim($v); return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): self { $this->status = strtolower(trim($v)); return $this; }
    public function getProvince(): string { return $this->province; }
    public function setProvince(string $v): self { $this->province = trim($v); return $this; }
    public function getIssuedAt(): ?\DateTimeImmutable { return $this->issuedAt; }
    public function setIssuedAt(?\DateTimeImmutable $v): self { $this->issuedAt = $v; return $this; }
    public function getExpiresAt(): ?\DateTimeImmutable { return $this->expiresAt; }
    public function setExpiresAt(?\DateTimeImmutable $v): self { $this->expiresAt = $v; return $this; }
    public function getSyncedAt(): \DateTimeImmutable { return $this->syncedAt; }
    public function touch(): self { $this->syncedAt = new \DateTimeImmutable(); return $this; }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
