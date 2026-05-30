<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\State\FarmStateProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'farm')]
#[ORM\Index(name: 'IDX_54C1B93E2C2AC5D3', columns: ['tenant_id'])]
#[ORM\Index(name: 'IDX_54C1B93E32C8A3DE', columns: ['organization_id'])]
#[ApiResource(
    operations: [
        new GetCollection(
            security: "is_granted('ROLE_SUPER_ADMIN') or is_granted('ROLE_ORG_ADMIN') or is_granted('ROLE_ORG_USER') or is_granted('ROLE_VIEWER')"
        ),
        new Get(
            security: "(is_granted('ROLE_SUPER_ADMIN') or is_granted('ROLE_ORG_ADMIN') or is_granted('ROLE_ORG_USER') or is_granted('ROLE_VIEWER')) and is_granted('TENANT_ACCESS', object)"
        ),
        new Post(
            processor: FarmStateProcessor::class,
            security: "is_granted('ROLE_SUPER_ADMIN') or is_granted('ROLE_ORG_ADMIN')"
        ),
        new Patch(
            processor: FarmStateProcessor::class,
            security: "(is_granted('ROLE_SUPER_ADMIN') or is_granted('ROLE_ORG_ADMIN')) and is_granted('TENANT_ACCESS', object)",
            securityPostDenormalize: "is_granted('ROLE_SUPER_ADMIN') or is_granted('TENANT_ACCESS', object)"
        ),
        // Soft-delete: sets archivedAt instead of removing the row
        new Delete(
            processor: FarmStateProcessor::class,
            security: "(is_granted('ROLE_SUPER_ADMIN') or is_granted('ROLE_ORG_ADMIN')) and is_granted('TENANT_ACCESS', object)"
        ),
    ],
    normalizationContext: ['groups' => ['farm:read']],
    denormalizationContext: ['groups' => ['farm:write']],
)]
class Farm
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['farm:read'])]
    private Uuid $id;

    /**
     * tenantId = Organization::$id
     * Injected automatically by TenantListener — DO NOT set manually
     */
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $tenantId;

    #[ORM\ManyToOne(targetEntity: Organization::class, inversedBy: 'farms')]
    #[ORM\JoinColumn(nullable: false)]
    private Organization $organization;

    #[ORM\Column(length: 255)]
    #[Groups(['farm:read', 'farm:write'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['farm:read', 'farm:write'])]
    #[Assert\Length(max: 2000)]
    private ?string $address = null;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['farm:read', 'farm:write'])]
    private ?float $surfaceM2 = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['farm:read'])]
    private ?\DateTimeImmutable $archivedAt = null;

    /** @var Collection<int, Room> */
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
    public function getArchivedAt(): ?\DateTimeImmutable { return $this->archivedAt; }
    public function setArchivedAt(?\DateTimeImmutable $archivedAt): self { $this->archivedAt = $archivedAt; return $this; }
    public function isArchived(): bool { return $this->archivedAt !== null; }
    /** @return Collection<int, Room> */
    public function getRooms(): Collection { return $this->rooms; }
}
