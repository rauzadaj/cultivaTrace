<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use App\Enum\DestructionStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

/**
 * DestructionIntent — intent to destroy a plant.
 *
 * Regulatory workflow (CA/USA Illinois):
 *   1. POST /api/plants/{id}/destroy → creates DestructionIntent (status: pending)
 *   2. Mandatory 7-day legal waiting period
 *   3. POST /api/destructions/{id}/confirm → finalizes with weight + 50% ratio
 *
 * Immutable rules:
 *   - canBeConfirmed() returns false before D+7
 *   - The non-cannabis ratio must be >= 0.50
 *   - Photos are required at confirmation
 */
#[ORM\Entity]
#[ORM\Table(name: 'destruction_intent')]
#[ApiResource(
    operations: [
        new GetCollection(
            security: "is_granted('ROLE_SUPER_ADMIN') or is_granted('ROLE_ORG_ADMIN') or is_granted('ROLE_ORG_USER') or is_granted('ROLE_VIEWER')"
        ),
        new Get(
            security: "(is_granted('ROLE_SUPER_ADMIN') or is_granted('ROLE_ORG_ADMIN') or is_granted('ROLE_ORG_USER') or is_granted('ROLE_VIEWER')) and is_granted('TENANT_ACCESS', object)"
        ),
        // Transitions via DestructionController only (POST /api/plants/{id}/destroy, POST /api/destructions/{id}/confirm)
    ],
    normalizationContext: ['groups' => ['destruction:read']],
    order: ['declaredAt' => 'DESC'],
)]
#[ApiFilter(SearchFilter::class, properties: ['plant' => 'exact', 'status' => 'exact'])]
#[ApiFilter(DateFilter::class, properties: ['declaredAt'])]
class DestructionIntent
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['destruction:read'])]
    private Uuid $id;

    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $tenantId;

    #[ORM\ManyToOne(targetEntity: Plant::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['destruction:read'])]
    private Plant $plant;

    #[ORM\Column(type: 'text')]
    #[Groups(['destruction:read'])]
    private string $reason;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['destruction:read'])]
    private \DateTimeImmutable $declaredAt;

    /** Minimum legal date = declaredAt + 7 days */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['destruction:read'])]
    private \DateTimeImmutable $legalDateMin;

    /** pending | confirmed | cancelled */
    #[ORM\Column(length: 50, enumType: DestructionStatus::class)]
    #[Groups(['destruction:read'])]
    private DestructionStatus $status = DestructionStatus::Pending;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[Groups(['destruction:read'])]
    private ?string $totalWeightG = null;

    /**
     * Non-cannabis material ratio >= 0.50 required
     * Ex: 0.55 = 55% non-cannabis materials mixed in
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    #[Groups(['destruction:read'])]
    private ?string $nonCannabisRatio = null;

    /** @var list<string>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['destruction:read'])]
    private ?array $photoUrls = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['destruction:read'])]
    private ?\DateTimeImmutable $confirmedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['destruction:read'])]
    private User $declaredBy;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['destruction:read'])]
    private ?User $confirmedBy = null;

    public function __construct(int $legalDelayDays = 7)
    {
        $this->id = Uuid::v4();
        $this->declaredAt = new \DateTimeImmutable();
        $this->legalDateMin = new \DateTimeImmutable(sprintf('+%d days', $legalDelayDays));
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

    #[Groups(['destruction:read'])]
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
    /** @return list<string>|null */
    public function getPhotoUrls(): ?array { return $this->photoUrls; }
    /** @param list<string>|null $u */
    public function setPhotoUrls(?array $u): self { $this->photoUrls = $u; return $this; }
    public function getConfirmedAt(): ?\DateTimeImmutable { return $this->confirmedAt; }
    public function setConfirmedAt(?\DateTimeImmutable $d): self { $this->confirmedAt = $d; return $this; }
    public function getDeclaredBy(): User { return $this->declaredBy; }
    public function setDeclaredBy(User $u): self { $this->declaredBy = $u; return $this; }
    public function getConfirmedBy(): ?User { return $this->confirmedBy; }
    public function setConfirmedBy(?User $u): self { $this->confirmedBy = $u; return $this; }
}
