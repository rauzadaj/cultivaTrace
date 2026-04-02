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
 * InputRecord — log immuable d'un intrant appliqué sur un plant.
 *
 * POST uniquement — jamais de modification après création.
 *
 * Pour les pesticides (inputType = pesticide) :
 *   - loqValue + loqUnit sont obligatoires si Health Canada CTS activé
 *   - testResult = fail déclenche la quarantaine automatique du plant
 *   - La règle des 7 jours Health Canada s'applique si testResult = fail
 */
#[ORM\Entity]
#[ORM\Table(name: 'input_record')]
#[ApiResource(operations: [
    new GetCollection(
        security: "is_granted('ROLE_SUPER_ADMIN') or is_granted('ROLE_ORG_ADMIN') or is_granted('ROLE_ORG_USER')"
    ),
    new Get(
        security: "(is_granted('ROLE_SUPER_ADMIN') or is_granted('ROLE_ORG_ADMIN') or is_granted('ROLE_ORG_USER')) and is_granted('TENANT_ACCESS', object)"
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

    // ── Champs LoQ (Health Canada — pesticides uniquement) ────────────────

    /**
     * Valeur mesurée lors du test laboratoire (mg/kg)
     * Obligatoire si inputType = pesticide ET client canadien
     */
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $loqValue = null;

    /**
     * Unité de la LoQ : mg/kg (standard Health Canada)
     */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $loqUnit = null;

    /**
     * Seuil de référence Health Canada pour cette substance (mg/kg)
     * Chargé depuis la table pesticide_reference
     */
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $loqThreshold = null;

    /**
     * pass | fail | pending | not_applicable
     * Calculé automatiquement : fail si loqValue > loqThreshold
     */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $testResult = null;

    /**
     * Nom du laboratoire ayant effectué l'analyse
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $labName = null;

    /**
     * Défini automatiquement si testResult = fail
     * Déclenche le timer 7 jours Health Canada
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
     * Calcule et applique automatiquement le testResult.
     * Appeler après avoir défini loqValue et loqThreshold.
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
     * Deadline Health Canada : 7 jours calendaires après testResult = fail
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
