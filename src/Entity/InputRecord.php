<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Metadata\ApiFilter;
use App\State\InputRecordStateProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * InputRecord — immutable log of an input applied to a plant.
 *
 * POST only — never modified after creation.
 *
 * For pesticides (inputType = pesticide):
 *   - loqValue + loqUnit are required when Health Canada CTS is enabled
 *   - testResult = fail triggers automatic plant quarantine
 *   - The Health Canada 7-day rule applies when testResult = fail
 */
#[ORM\Entity]
#[ORM\Table(name: 'input_record')]
#[ApiResource(operations: [
    new GetCollection(
        security: "is_granted('ROLE_SUPER_ADMIN') or is_granted('ROLE_ORG_ADMIN') or is_granted('ROLE_ORG_USER') or is_granted('ROLE_VIEWER')"
    ),
    new Get(
        security: "(is_granted('ROLE_SUPER_ADMIN') or is_granted('ROLE_ORG_ADMIN') or is_granted('ROLE_ORG_USER') or is_granted('ROLE_VIEWER')) and is_granted('TENANT_ACCESS', object)"
    ),
    new Post(
        processor: InputRecordStateProcessor::class,
        security: "is_granted('ROLE_SUPER_ADMIN') or is_granted('ROLE_ORG_ADMIN') or is_granted('ROLE_ORG_USER')"
    ),
])]
#[ApiFilter(SearchFilter::class, properties: [
    'plant.id'   => 'exact',
    'inputType'  => 'exact',
    'testResult' => 'exact',
])]
#[ApiFilter(DateFilter::class, properties: ['appliedAt'])]
class InputRecord
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

    /**
     * nutrient | pesticide | water | energy
     */
    #[ORM\Column(length: 100)]
    #[Assert\Choice(choices: ['nutrient', 'pesticide', 'water', 'energy'])]
    private string $inputType;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $productName;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 3)]
    #[Assert\Positive]
    private string $quantity;

    /** ml | g | L | kg | kWh */
    #[ORM\Column(length: 50)]
    private string $unit;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $appliedAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $appliedBy;

    // ── LoQ fields (Health Canada — pesticides only) ──────────────────────

    /**
     * Measured value from the laboratory test (mg/kg)
     * Required when inputType = pesticide AND Canadian client
     */
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $loqValue = null;

    /**
     * LoQ unit: mg/kg (Health Canada standard)
     */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $loqUnit = null;

    /**
     * Health Canada reference threshold for this substance (mg/kg)
     * Loaded from the pesticide_reference table
     */
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $loqThreshold = null;

    /**
     * pass | fail | pending | not_applicable
     * Computed automatically: fail if loqValue > loqThreshold
     */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $testResult = null;

    /**
     * Name of the laboratory that performed the analysis
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $labName = null;

    /**
     * Set automatically when testResult = fail
     * Starts the Health Canada 7-day timer
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $quarantinedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->appliedAt = new \DateTimeImmutable();
    }

    /**
     * Computes and applies testResult automatically.
     * Call after setting loqValue and loqThreshold.
     */
    public function computeTestResult(): void
    {
        if ($this->inputType !== 'pesticide' || $this->loqValue === null || $this->loqThreshold === null) {
            $this->testResult = 'not_applicable';
            return;
        }

        if ($this->loqValue > $this->loqThreshold) {
            $this->testResult = 'fail';
            $this->quarantinedAt = new \DateTimeImmutable();
        } else {
            $this->testResult = 'pass';
            $this->quarantinedAt = null;
        }
    }

    public function isQuarantined(): bool
    {
        return $this->testResult === 'fail' && $this->quarantinedAt !== null;
    }

    /**
     * Health Canada deadline: 7 calendar days after testResult = fail
     */
    public function getHealthCanadaReportDeadline(): ?\DateTimeImmutable
    {
        if (!$this->isQuarantined()) return null;
        return $this->quarantinedAt->modify('+7 days');
    }

    public function getId(): Uuid { return $this->id; }
    public function getTenantId(): Uuid { return $this->tenantId; }
    public function setTenantId(Uuid $id): self { $this->tenantId = $id; return $this; }
    public function getPlant(): Plant { return $this->plant; }
    public function setPlant(Plant $plant): self { $this->plant = $plant; return $this; }
    public function getInputType(): string { return $this->inputType; }
    public function setInputType(string $t): self { $this->inputType = $t; return $this; }
    public function getProductName(): string { return $this->productName; }
    public function setProductName(string $n): self { $this->productName = $n; return $this; }
    public function getQuantity(): string { return $this->quantity; }
    public function setQuantity(string $q): self { $this->quantity = $q; return $this; }
    public function getUnit(): string { return $this->unit; }
    public function setUnit(string $u): self { $this->unit = $u; return $this; }
    public function getAppliedAt(): \DateTimeImmutable { return $this->appliedAt; }
    public function setAppliedAt(\DateTimeImmutable $d): self { $this->appliedAt = $d; return $this; }
    public function getAppliedBy(): User { return $this->appliedBy; }
    public function setAppliedBy(User $u): self { $this->appliedBy = $u; return $this; }
    public function getLoqValue(): ?float { return $this->loqValue; }
    public function setLoqValue(?float $v): self { $this->loqValue = $v; return $this; }
    public function getLoqUnit(): ?string { return $this->loqUnit; }
    public function setLoqUnit(?string $u): self { $this->loqUnit = $u; return $this; }
    public function getLoqThreshold(): ?float { return $this->loqThreshold; }
    public function setLoqThreshold(?float $t): self { $this->loqThreshold = $t; return $this; }
    public function getTestResult(): ?string { return $this->testResult; }
    public function setTestResult(?string $r): self { $this->testResult = $r; return $this; }
    public function getLabName(): ?string { return $this->labName; }
    public function setLabName(?string $n): self { $this->labName = $n; return $this; }
    public function getQuarantinedAt(): ?\DateTimeImmutable { return $this->quarantinedAt; }
    public function setQuarantinedAt(?\DateTimeImmutable $d): self { $this->quarantinedAt = $d; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $n): self { $this->notes = $n; return $this; }
}
