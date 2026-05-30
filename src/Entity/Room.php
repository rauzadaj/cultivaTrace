<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Enum\RoomType;
use App\State\RoomStateProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Room represents a grow room attached to a farm.
 */
#[ORM\Entity]
#[ORM\Table(name: 'room')]
#[ApiResource(operations: [
    new GetCollection(
        security: "is_granted('ROLE_SUPER_ADMIN') or is_granted('ROLE_ORG_ADMIN') or is_granted('ROLE_ORG_USER') or is_granted('ROLE_VIEWER')"
    ),
    new Get(
        security: "(is_granted('ROLE_SUPER_ADMIN') or is_granted('ROLE_ORG_ADMIN') or is_granted('ROLE_ORG_USER') or is_granted('ROLE_VIEWER')) and is_granted('TENANT_ACCESS', object)"
    ),
    new Post(
        processor: RoomStateProcessor::class,
        security: "is_granted('ROLE_SUPER_ADMIN') or is_granted('ROLE_ORG_ADMIN')"
    ),
    new Patch(
        processor: RoomStateProcessor::class,
        security: "(is_granted('ROLE_SUPER_ADMIN') or is_granted('ROLE_ORG_ADMIN')) and is_granted('TENANT_ACCESS', object)",
        securityPostDenormalize: "is_granted('ROLE_SUPER_ADMIN') or is_granted('TENANT_ACCESS', object)"
    ),
])]
class Room
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private Uuid $id;

    #[ORM\Column(type: UuidType::NAME)]
    #[ApiProperty(readable: false, writable: false)]
    private Uuid $tenantId;

    #[ORM\ManyToOne(targetEntity: Farm::class, inversedBy: 'rooms')]
    #[ORM\JoinColumn(nullable: false)]
    private Farm $farm;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /**
     * Type de salle : veg | flower | drying | clone | mixed
     */
    #[ORM\Column(length: 50, enumType: RoomType::class)]
    private RoomType $type = RoomType::Veg;

    #[ORM\Column(type: 'integer')]
    private int $capacityMax = 100;

    /** @var Collection<int, Plant> */
    #[ORM\OneToMany(targetEntity: Plant::class, mappedBy: 'room')]
    private Collection $plants;

    /** @var Collection<int, Sensor> */
    #[ORM\OneToMany(targetEntity: Sensor::class, mappedBy: 'room')]
    private Collection $sensors;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->plants = new ArrayCollection();
        $this->sensors = new ArrayCollection();
    }

    public function getId(): Uuid { return $this->id; }
    public function getTenantId(): Uuid { return $this->tenantId; }
    public function setTenantId(Uuid $tenantId): self { $this->tenantId = $tenantId; return $this; }
    public function getFarm(): Farm { return $this->farm; }
    public function setFarm(Farm $farm): self { $this->farm = $farm; return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $d): self { $this->description = $d; return $this; }
    public function getType(): RoomType { return $this->type; }
    public function setType(RoomType $type): self { $this->type = $type; return $this; }
    public function getCapacityMax(): int { return $this->capacityMax; }
    public function setCapacityMax(int $cap): self { $this->capacityMax = $cap; return $this; }
    /** @return Collection<int, Plant> */
    public function getPlants(): Collection { return $this->plants; }
    /** @return Collection<int, Sensor> */
    public function getSensors(): Collection { return $this->sensors; }

    public function getActivePlantCount(): int
    {
        return $this->plants->filter(
            fn(Plant $p) => $p->getStatus()->value === 'active'
        )->count();
    }

    public function getOccupancyRate(): float
    {
        if ($this->capacityMax === 0) return 0.0;
        return round($this->getActivePlantCount() / $this->capacityMax * 100, 1);
    }
}
