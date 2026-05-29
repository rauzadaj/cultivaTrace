<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

/**
 * PlantEvent = immutable audit trail, append-only.
 *
 * ⚠️ ABSOLUTE RULE: never UPDATE or DELETE on this table.
 * Always go through PlantEventRepository::appendEvent().
 *
 * Hash-chaining guarantees integrity:
 *   hashSelf = SHA-256(id + JSON(payload) + occurredAt.format('U') + hashPrevious)
 *
 * Valid event types:
 *   germination | stage_change | note | photo | input_record |
 *   harvest | destruction_intent | destruction_confirmed | room_move
 */
#[ORM\Entity(repositoryClass: \App\Repository\PlantEventRepository::class)]
#[ORM\Table(name: 'plant_event')]
#[ApiResource(
    operations: [
        new GetCollection(
            security: "is_granted('ROLE_SUPER_ADMIN') or is_granted('ROLE_ORG_ADMIN') or is_granted('ROLE_ORG_USER') or is_granted('ROLE_VIEWER')"
        ),
        new Get(
            security: "(is_granted('ROLE_SUPER_ADMIN') or is_granted('ROLE_ORG_ADMIN') or is_granted('ROLE_ORG_USER') or is_granted('ROLE_VIEWER')) and is_granted('TENANT_ACCESS', object)"
        ),
    ],
    normalizationContext: ['groups' => ['plant_event:read']],
)]
#[ApiFilter(SearchFilter::class, properties: [
    'plant.id' => 'exact',
    'eventType' => 'exact',
    'user.id' => 'exact',
])]
#[ApiFilter(DateFilter::class, properties: ['occurredAt'])]
#[ApiFilter(OrderFilter::class, properties: ['occurredAt'])]
class PlantEvent
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[Groups(['plant_event:read'])]
    private Uuid $id;

    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $tenantId;

    #[ORM\ManyToOne(targetEntity: Plant::class, inversedBy: 'events')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['plant_event:read'])]
    private Plant $plant;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['plant_event:read'])]
    private User $user;

    /**
     * Valeurs valides : germination | stage_change | note | photo |
     * input_record | harvest | destruction_intent | destruction_confirmed |
     * room_move | legacy_activity
     */
    #[ORM\Column(length: 100)]
    #[Groups(['plant_event:read'])]
    private string $eventType;

    /**
     * Structure selon eventType :
     * - stage_change: {"from": "vegetation", "to": "flowering"}
     * - room_move: {"from_room_id": "...", "to_room_id": "..."}
     * - input_record: {"product": "...", "quantity": 10, "unit": "ml"}
     * - destruction_intent: {"reason": "...", "planned_date": "..."}
     * - destruction_confirmed: {"gross_weight_g": 100, "non_cannabis_ratio": 0.55}
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['plant_event:read'])]
    private ?array $payload = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['plant_event:read'])]
    private ?string $notes = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['plant_event:read'])]
    private ?array $photoUrls = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['plant_event:read'])]
    private \DateTimeImmutable $occurredAt;

    #[ORM\Column(length: 64)]
    #[Groups(['plant_event:read'])]
    private string $hashPrevious = '0000000000000000000000000000000000000000000000000000000000000000';

    #[ORM\Column(length: 64)]
    #[Groups(['plant_event:read'])]
    private string $hashSelf;

    // Stored as HMAC-SHA256 pseudonym — raw IP is never persisted (GDPR compliance)
    #[ORM\Column(length: 64)]
    private string $ipAddress = '';

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid { return $this->id; }
    public function getTenantId(): Uuid { return $this->tenantId; }
    public function setTenantId(Uuid $tenantId): self { $this->tenantId = $tenantId; return $this; }
    public function getPlant(): Plant { return $this->plant; }
    public function setPlant(Plant $plant): self { $this->plant = $plant; return $this; }
    public function getUser(): User { return $this->user; }
    public function setUser(User $user): self { $this->user = $user; return $this; }
    public function getEventType(): string { return $this->eventType; }
    public function setEventType(string $type): self { $this->eventType = $type; return $this; }
    public function getPayload(): ?array { return $this->payload; }
    public function setPayload(?array $payload): self { $this->payload = $payload; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): self { $this->notes = $notes; return $this; }
    public function getPhotoUrls(): ?array { return $this->photoUrls; }
    public function setPhotoUrls(?array $urls): self { $this->photoUrls = $urls; return $this; }
    public function getOccurredAt(): \DateTimeImmutable { return $this->occurredAt; }
    public function setOccurredAt(\DateTimeImmutable $date): self { $this->occurredAt = $date; return $this; }
    public function getHashPrevious(): string { return $this->hashPrevious; }
    public function setHashPrevious(string $hash): self { $this->hashPrevious = $hash; return $this; }
    public function getHashSelf(): string { return $this->hashSelf; }
    public function setHashSelf(string $hash): self { $this->hashSelf = $hash; return $this; }
    public function getIpAddress(): string { return $this->ipAddress; }
    public function setIpAddress(string $ip): self { $this->ipAddress = $ip; return $this; }
}
