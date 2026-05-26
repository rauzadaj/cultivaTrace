<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use App\State\HarvestRecordPatchProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * HarvestRecord — harvest data for a plant.
 * 1-to-1 relationship with Plant.
 *
 * Created only via POST /api/plants/{id}/harvest (HarvestController)
 * in an atomic transaction. Never create directly via the API.
 */
#[ORM\Entity]
#[ORM\Table(name: 'harvest_record')]
#[Assert\Callback]
#[ApiResource(
    operations: [
        new GetCollection(
            security: "is_granted('ROLE_SUPER_ADMIN') or is_granted('ROLE_ORG_ADMIN') or is_granted('ROLE_ORG_USER')"
        ),
        new Get(
            security: "(is_granted('ROLE_SUPER_ADMIN') or is_granted('ROLE_ORG_ADMIN') or is_granted('ROLE_ORG_USER')) and is_granted('TENANT_ACCESS', object)"
        ),
        new Patch(
            security: "(is_granted('ROLE_SUPER_ADMIN') or is_granted('ROLE_ORG_ADMIN')) and is_granted('TENANT_ACCESS', object)",
            denormalizationContext: ['groups' => ['harvest:write']],
            processor: HarvestRecordPatchProcessor::class,
        ),
        // POST only via HarvestController — no direct operation
    ],
    normalizationContext: ['groups' => ['harvest:read']],
    order: ['harvestedAt' => 'DESC'],
)]
#[ApiFilter(SearchFilter::class, properties: ['plant' => 'exact'])]
#[ApiFilter(DateFilter::class, properties: ['harvestedAt'])]
class HarvestRecord
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['harvest:read'])]
    private Uuid $id;

    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $tenantId;

    #[ORM\OneToOne(targetEntity: Plant::class, inversedBy: 'harvestRecord')]
    #[ORM\JoinColumn(nullable: false, unique: true)]
    #[Groups(['harvest:read'])]
    private Plant $plant;

    /** Gross weight in grams (before trim) */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\Positive]
    #[Groups(['harvest:read', 'harvest:write'])]
    private string $grossWeightG;

    /** Net weight in grams (after trim, ready for sale/analysis) */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\Positive]
    #[Groups(['harvest:read', 'harvest:write'])]
    private string $netWeightG;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Groups(['harvest:read'])]
    private \DateTimeImmutable $harvestedAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['harvest:read'])]
    private User $harvestedBy;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['harvest:read', 'harvest:write'])]
    private ?string $notes = null;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->harvestedAt = new \DateTimeImmutable();
    }

    /** Net/gross weight ratio as a percentage */
    public function getYieldRatio(): float
    {
        if ((float) $this->grossWeightG === 0.0) return 0.0;
        return round((float) $this->netWeightG / (float) $this->grossWeightG * 100, 1);
    }

    public function getId(): Uuid { return $this->id; }
    public function getTenantId(): Uuid { return $this->tenantId; }
    public function setTenantId(Uuid $id): self { $this->tenantId = $id; return $this; }
    public function getPlant(): Plant { return $this->plant; }
    public function setPlant(Plant $plant): self { $this->plant = $plant; return $this; }
    public function getGrossWeightG(): string { return $this->grossWeightG; }
    public function setGrossWeightG(string $w): self { $this->grossWeightG = $w; return $this; }
    public function getNetWeightG(): string { return $this->netWeightG; }
    public function setNetWeightG(string $w): self { $this->netWeightG = $w; return $this; }
    public function getHarvestedAt(): \DateTimeImmutable { return $this->harvestedAt; }
    public function setHarvestedAt(\DateTimeImmutable $d): self { $this->harvestedAt = $d; return $this; }
    public function getHarvestedBy(): User { return $this->harvestedBy; }
    public function setHarvestedBy(User $u): self { $this->harvestedBy = $u; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $n): self { $this->notes = $n; return $this; }

    public function validateWeights(ExecutionContextInterface $context): void
    {
        if (isset($this->grossWeightG, $this->netWeightG)
            && (float) $this->netWeightG > (float) $this->grossWeightG
        ) {
            $context->buildViolation('Net weight cannot exceed gross weight.')
                ->atPath('netWeightG')
                ->addViolation();
        }
    }
}
