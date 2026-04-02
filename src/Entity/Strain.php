<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use App\State\StrainStateProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'strain')]
#[ApiResource(operations: [
    new GetCollection(
        security: "is_granted('ROLE_SUPER_ADMIN') or is_granted('ROLE_ORG_ADMIN') or is_granted('ROLE_ORG_USER')"
    ),
    new Get(
        security: "(is_granted('ROLE_SUPER_ADMIN') or is_granted('ROLE_ORG_ADMIN') or is_granted('ROLE_ORG_USER')) and is_granted('TENANT_ACCESS', object)"
    ),
    new Post(
        processor: StrainStateProcessor::class,
        security: "is_granted('ROLE_SUPER_ADMIN') or is_granted('ROLE_ORG_ADMIN')"
    ),
    new Patch(
        processor: StrainStateProcessor::class,
        security: "(is_granted('ROLE_SUPER_ADMIN') or is_granted('ROLE_ORG_ADMIN')) and is_granted('TENANT_ACCESS', object)",
        securityPostDenormalize: "is_granted('ROLE_SUPER_ADMIN') or is_granted('TENANT_ACCESS', object)"
    ),
    new Delete(
        security: "(is_granted('ROLE_SUPER_ADMIN') or is_granted('ROLE_ORG_ADMIN')) and is_granted('TENANT_ACCESS', object)"
    ),
])]
#[ApiFilter(SearchFilter::class, properties: [
    'name'         => 'partial',
    'genetics'     => 'exact',
    'cannabisType' => 'exact',
])]
class Strain
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private Uuid $id;

    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $tenantId;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $name;

    /** indica | sativa | hybrid */
    #[ORM\Column(length: 50)]
    #[Assert\Choice(choices: ['indica', 'sativa', 'hybrid'])]
    private string $genetics = 'hybrid';

    /**
     * hemp | marijuana
     * Requis pour la distinction post-Hemp Loophole (deadline : 12 novembre 2026)
     * hemp : THC <= 0.3% — marijuana : THC > 0.3%
     */
    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: ['hemp', 'marijuana'])]
    private string $cannabisType = 'marijuana';

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $thcPercentage = null;

    #[ORM\Column(nullable: true)]
    private ?int $floweringDays = null;

    /**
     * Paramètres de culture recommandés — utilisés pour les alertes VPD :
     * {
     *   "temp_min": 20, "temp_max": 28,
     *   "humidity_min": 40, "humidity_max": 70,
     *   "vpd_veg_min": 0.8, "vpd_veg_max": 1.2,
     *   "vpd_flower_min": 1.0, "vpd_flower_max": 1.5
     * }
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $growParams = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    public function __construct()
    {
        $this->id = Uuid::v4();
    }

    public function getId(): Uuid { return $this->id; }
    public function getTenantId(): Uuid { return $this->tenantId; }
    public function setTenantId(Uuid $id): self { $this->tenantId = $id; return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $n): self { $this->name = $n; return $this; }
    public function getGenetics(): string { return $this->genetics; }
    public function setGenetics(string $g): self { $this->genetics = $g; return $this; }
    public function getCannabisType(): string { return $this->cannabisType; }
    public function setCannabisType(string $t): self { $this->cannabisType = $t; return $this; }
    public function getThcPercentage(): ?float { return $this->thcPercentage; }
    public function setThcPercentage(?float $p): self { $this->thcPercentage = $p; return $this; }
    public function getFloweringDays(): ?int { return $this->floweringDays; }
    public function setFloweringDays(?int $d): self { $this->floweringDays = $d; return $this; }
    public function getGrowParams(): ?array { return $this->growParams; }
    public function setGrowParams(?array $p): self { $this->growParams = $p; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $n): self { $this->notes = $n; return $this; }

    public function isHempCompliant(): bool
    {
        if ($this->cannabisType !== 'hemp') return true;
        return $this->thcPercentage !== null && $this->thcPercentage <= 0.3;
    }
}
