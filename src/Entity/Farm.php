<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'farm')]
#[ApiResource(operations: [
    new GetCollection(),
    new Get(),
    new Post(),
    new Patch(),
    // pas de Delete — soft delete uniquement
])]
class Farm
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private Uuid $id;

    /**
     * tenantId = Organization::$id
     * Injecté automatiquement par TenantListener — NE PAS setter manuellement
     */
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $tenantId;

    #[ORM\ManyToOne(targetEntity: Organization::class, inversedBy: 'farms')]
    #[ORM\JoinColumn(nullable: false)]
    private Organization $organization;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $address = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $surfaceM2 = null;

    #[ORM\OneToMany(targetEntity: Room::class, mappedBy: 'farm')]
    private Collection $rooms;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->rooms = new ArrayCollection();
    }

    public function getId(): Uuid { return $this->id; }
    public function getTenantId(): Uuid { return $this->tenantId; }
    public function setTenantId(Uuid $tenantId): self { $this->tenantId = $tenantId; return $this; }
    public function getOrganization(): Organization { return $this->organization; }
    public function setOrganization(Organization $org): self { $this->organization = $org; return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }
    public function getAddress(): ?string { return $this->address; }
    public function setAddress(?string $address): self { $this->address = $address; return $this; }
    public function getSurfaceM2(): ?float { return $this->surfaceM2; }
    public function setSurfaceM2(?float $m2): self { $this->surfaceM2 = $m2; return $this; }
    public function getRooms(): Collection { return $this->rooms; }
}
