<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Enum\DestructionStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * DestructionIntent — intention de destruction d'un plant.
 *
 * Workflow réglementaire (CA/USA Illinois) :
 *   1. POST /api/plants/{id}/destroy → crée DestructionIntent (status: pending)
 *   2. Attente 7 jours légaux minimum
 *   3. POST /api/destructions/{id}/confirm → finalise avec pesée + ratio 50%
 *
 * Règles non-contournables :
 *   - canBeConfirmed() retourne false avant J+7
 *   - Le ratio non-cannabis doit être >= 0.50
 *   - Les photos sont obligatoires à la confirmation
 */
#[ORM\Entity]
#[ORM\Table(name: 'destruction_intent')]
#[ApiResource(operations: [
    new GetCollection(
        security: "is_granted('ROLE_SUPER_ADMIN') or is_granted('ROLE_ORG_ADMIN') or is_granted('ROLE_ORG_USER')"
    ),
    new Get(
        security: "(is_granted('ROLE_SUPER_ADMIN') or is_granted('ROLE_ORG_ADMIN') or is_granted('ROLE_ORG_USER')) and is_granted('TENANT_ACCESS', object)"
    ),
    // Actions via DestructionController uniquement
])]
class DestructionIntent
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private Uuid $id;

    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $tenantId;

    #[ORM\ManyToOne(targetEntity: Plant::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Plant $plant;

    #[ORM\Column(type: 'text')]
    private string $reason;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $declaredAt;

    /** Date légale minimum = declaredAt + 7 jours */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $legalDateMin;

    /** pending | confirmed | cancelled */
    #[ORM\Column(length: 50, enumType: DestructionStatus::class)]
    private DestructionStatus $status = DestructionStatus::Pending;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $totalWeightG = null;

    /**
     * Ratio matières non-cannabis >= 0.50 obligatoire
     * Ex: 0.55 = 55% de matières non-cannabiques mélangées
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $nonCannabisRatio = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $photoUrls = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $confirmedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $declaredBy;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $confirmedBy = null;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->declaredAt = new \DateTimeImmutable();
        $this->legalDateMin = new \DateTimeImmutable('+7 days');
    }

    public function canBeConfirmed(): bool
    {
        return $this->status === DestructionStatus::Pending
            && new \DateTimeImmutable() >= $this->legalDateMin;
    }

    public function isNonCannabisRatioValid(): bool
    {
        if ($this->nonCannabisRatio === null) return false;
        return (float) $this->nonCannabisRatio >= 0.50;
    }

    public function getDaysRemaining(): int
    {
        $now = new \DateTimeImmutable();
        if ($now >= $this->legalDateMin) return 0;
        return (int) $now->diff($this->legalDateMin)->days;
    }

    public function getId(): Uuid { return $this->id; }
    public function getTenantId(): Uuid { return $this->tenantId; }
    public function setTenantId(Uuid $id): self { $this->tenantId = $id; return $this; }
    public function getPlant(): Plant { return $this->plant; }
    public function setPlant(Plant $plant): self { $this->plant = $plant; return $this; }
    public function getReason(): string { return $this->reason; }
    public function setReason(string $r): self { $this->reason = $r; return $this; }
    public function getDeclaredAt(): \DateTimeImmutable { return $this->declaredAt; }
    public function getLegalDateMin(): \DateTimeImmutable { return $this->legalDateMin; }
    public function getStatus(): DestructionStatus { return $this->status; }
    public function setStatus(DestructionStatus $s): self { $this->status = $s; return $this; }
    public function getTotalWeightG(): ?string { return $this->totalWeightG; }
    public function setTotalWeightG(?string $w): self { $this->totalWeightG = $w; return $this; }
    public function getNonCannabisRatio(): ?string { return $this->nonCannabisRatio; }
    public function setNonCannabisRatio(?string $r): self { $this->nonCannabisRatio = $r; return $this; }
    public function getPhotoUrls(): ?array { return $this->photoUrls; }
    public function setPhotoUrls(?array $u): self { $this->photoUrls = $u; return $this; }
    public function getConfirmedAt(): ?\DateTimeImmutable { return $this->confirmedAt; }
    public function setConfirmedAt(?\DateTimeImmutable $d): self { $this->confirmedAt = $d; return $this; }
    public function getDeclaredBy(): User { return $this->declaredBy; }
    public function setDeclaredBy(User $u): self { $this->declaredBy = $u; return $this; }
    public function getConfirmedBy(): ?User { return $this->confirmedBy; }
    public function setConfirmedBy(?User $u): self { $this->confirmedBy = $u; return $this; }
}
