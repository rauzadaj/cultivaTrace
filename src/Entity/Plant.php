<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use App\Enum\PlantStage;
use App\Enum\PlantStatus;
use App\State\PlantStateProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Plant est l'entité centrale de CannaSaaS.
 * Chaque plant a un historique complet via PlantEvent (append-only).
 *
 * IMPORTANT : ne jamais modifier un PlantEvent existant.
 * Utiliser PlantEventRepository::appendEvent() pour toute action sur un plant.
 */
#[ORM\Entity]
#[ORM\Table(name: 'plant')]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(processor: PlantStateProcessor::class),
        new Patch(processor: PlantStateProcessor::class), // uniquement stage, room, rfidTag — pas les données de création
        // pas de Delete — archivage uniquement via PATCH status=archived
    ],
    normalizationContext: ['groups' => ['plant:read']],
    denormalizationContext: ['groups' => ['plant:write']],
)]
#[ApiFilter(SearchFilter::class, properties: [
    'room.id' => 'exact',
    'strain.id' => 'exact',
    'stage' => 'exact',
    'status' => 'exact',
])]
#[ApiFilter(DateFilter::class, properties: ['germinatedAt'])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'germinatedAt'])]
class Plant
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['plant:read'])]
    private Uuid $id;

    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $tenantId;

    #[ORM\ManyToOne(targetEntity: Room::class, inversedBy: 'plants')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['plant:read', 'plant:write'])]
    private Room $room;

    #[ORM\ManyToOne(targetEntity: Strain::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['plant:read', 'plant:write'])]
    private ?Strain $strain = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['plant:read', 'plant:write'])]
    private ?string $rfidTag = null;

    #[ORM\Column(length: 50, enumType: PlantStage::class)]
    #[Groups(['plant:read', 'plant:write'])]
    private PlantStage $stage = PlantStage::GERMINATION;

    #[ORM\Column(length: 50, enumType: PlantStatus::class)]
    #[Groups(['plant:read', 'plant:write'])]
    private PlantStatus $status = PlantStatus::ACTIVE;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Groups(['plant:read', 'plant:write'])]
    private \DateTimeImmutable $germinatedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['plant:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $createdBy;

    #[ORM\OneToMany(targetEntity: PlantEvent::class, mappedBy: 'plant')]
    #[ORM\OrderBy(['occurredAt' => 'DESC'])]
    private Collection $events;

    #[ORM\OneToOne(targetEntity: HarvestRecord::class, mappedBy: 'plant')]
    private ?HarvestRecord $harvestRecord = null;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->createdAt = new \DateTimeImmutable();
        $this->events = new ArrayCollection();
    }

    public function getId(): Uuid { return $this->id; }
    public function getTenantId(): Uuid { return $this->tenantId; }
    public function setTenantId(Uuid $tenantId): self { $this->tenantId = $tenantId; return $this; }
    public function getRoom(): Room { return $this->room; }
    public function setRoom(Room $room): self { $this->room = $room; return $this; }
    public function getStrain(): ?Strain { return $this->strain; }
    public function setStrain(?Strain $strain): self { $this->strain = $strain; return $this; }
    public function getRfidTag(): ?string { return $this->rfidTag; }
    public function setRfidTag(?string $tag): self { $this->rfidTag = $tag; return $this; }
    public function getStage(): PlantStage { return $this->stage; }
    public function setStage(PlantStage $stage): self { $this->stage = $stage; return $this; }
    public function getStatus(): PlantStatus { return $this->status; }
    public function setStatus(PlantStatus $status): self { $this->status = $status; return $this; }
    public function getGerminatedAt(): \DateTimeImmutable { return $this->germinatedAt; }
    public function setGerminatedAt(\DateTimeImmutable $date): self { $this->germinatedAt = $date; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getCreatedBy(): User { return $this->createdBy; }
    public function setCreatedBy(User $user): self { $this->createdBy = $user; return $this; }
    public function getEvents(): Collection { return $this->events; }
    public function getHarvestRecord(): ?HarvestRecord { return $this->harvestRecord; }
    #[Groups(['plant:read'])]
    public function getAgeInDays(): int
    {
        return (int) $this->germinatedAt->diff(new \DateTimeImmutable())->days;
    }

    public function isActive(): bool
    {
        return $this->status === PlantStatus::ACTIVE;
    }
}
