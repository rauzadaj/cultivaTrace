<?php

declare(strict_types=1);

namespace App\Domain\Plant;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'domain_plant')]
#[ORM\Index(name: 'idx_domain_plant_organization_id', columns: ['organization_id'])]
#[ORM\Index(name: 'idx_domain_plant_status', columns: ['status'])]
#[ORM\Index(name: 'idx_domain_plant_planted_at', columns: ['planted_at'])]
class Plant
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(name: 'organization_id', type: UuidType::NAME)]
    private Uuid $organizationId;

    #[ORM\Column(name: 'batch_id', type: UuidType::NAME, nullable: true)]
    private ?Uuid $batchId;

    #[ORM\Column(length: 255)]
    private string $strain;

    #[ORM\Column(name: 'origin_type', length: 16, enumType: PlantOriginType::class)]
    private PlantOriginType $originType;

    #[ORM\Column(length: 32, enumType: PlantStatus::class)]
    private PlantStatus $status;

    #[ORM\Column(name: 'planted_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $plantedAt;

    #[ORM\Column(length: 255)]
    private string $location;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    private function __construct(
        Uuid $id,
        Uuid $organizationId,
        ?Uuid $batchId,
        string $strain,
        PlantOriginType $originType,
        PlantStatus $status,
        \DateTimeImmutable $plantedAt,
        string $location,
        ?string $notes,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ) {
        $this->id = $id;
        $this->organizationId = $organizationId;
        $this->batchId = $batchId;
        $this->strain = $strain;
        $this->originType = $originType;
        $this->status = $status;
        $this->plantedAt = $plantedAt;
        $this->location = $location;
        $this->notes = $notes;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public static function create(
        Uuid $organizationId,
        string $strain,
        PlantOriginType $originType,
        \DateTimeImmutable $plantedAt,
        string $location,
        ?Uuid $batchId = null,
        ?string $notes = null,
    ): self {
        $now = new \DateTimeImmutable();

        return new self(
            Uuid::v4(),
            $organizationId,
            $batchId,
            trim($strain),
            $originType,
            PlantStatus::GERMINATION,
            $plantedAt,
            trim($location),
            $notes,
            $now,
            $now,
        );
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getOrganizationId(): Uuid
    {
        return $this->organizationId;
    }

    public function getBatchId(): ?Uuid
    {
        return $this->batchId;
    }

    public function getStrain(): string
    {
        return $this->strain;
    }

    public function getOriginType(): PlantOriginType
    {
        return $this->originType;
    }

    public function getStatus(): PlantStatus
    {
        return $this->status;
    }

    public function getStatusValue(): string
    {
        return $this->status->value;
    }

    public function getPlantedAt(): \DateTimeImmutable
    {
        return $this->plantedAt;
    }

    public function getLocation(): string
    {
        return $this->location;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function relocate(string $location): void
    {
        $this->location = trim($location);
        $this->touch();
    }

    public function updateNotes(?string $notes): void
    {
        $this->notes = $notes;
        $this->touch();
    }

    public function applyWorkflowStatus(string $status): void
    {
        $this->status = PlantStatus::from($status);
        $this->touch();
    }

    public function setStatusValue(string $status): void
    {
        $this->applyWorkflowStatus($status);
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
