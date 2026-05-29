<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\AlertRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: AlertRepository::class)]
#[ORM\Table(name: 'alert')]
#[ORM\Index(columns: ['tenant_id', 'acknowledged_at', 'created_at'], name: 'idx_alert_tenant_ack_created')]
#[ORM\Index(name: 'IDX_7C9B057754177093', columns: ['sensor_id'])]
#[ApiResource(
    operations: [
        new GetCollection(
            security: "is_granted('ROLE_SUPER_ADMIN') or is_granted('ROLE_ORG_ADMIN') or is_granted('ROLE_ORG_USER') or is_granted('ROLE_VIEWER')"
        ),
        new Get(
            security: "(is_granted('ROLE_SUPER_ADMIN') or is_granted('ROLE_ORG_ADMIN') or is_granted('ROLE_ORG_USER') or is_granted('ROLE_VIEWER')) and is_granted('TENANT_ACCESS', object)"
        ),
    ],
    normalizationContext: ['groups' => ['alert:read']],
    order: ['createdAt' => 'DESC'],
)]
class Alert
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['alert:read'])]
    private Uuid $id;

    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $tenantId;

    #[ORM\Column(length: 64)]
    #[Groups(['alert:read'])]
    private string $type;

    #[ORM\Column(length: 32)]
    #[Groups(['alert:read'])]
    private string $severity = 'warning';

    #[ORM\Column(length: 160)]
    #[Groups(['alert:read'])]
    private string $title;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['alert:read'])]
    private string $message;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['alert:read'])]
    private ?string $context = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['alert:read'])]
    private ?array $metadata = null;

    #[ORM\ManyToOne(targetEntity: Sensor::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Sensor $sensor = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['alert:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['alert:read'])]
    private ?\DateTimeImmutable $acknowledgedAt = null;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid { return $this->id; }
    public function getTenantId(): Uuid { return $this->tenantId; }
    public function setTenantId(Uuid $tenantId): self { $this->tenantId = $tenantId; return $this; }
    public function getType(): string { return $this->type; }
    public function setType(string $type): self { $this->type = $type; return $this; }
    public function getSeverity(): string { return $this->severity; }
    public function setSeverity(string $severity): self { $this->severity = $severity; return $this; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }
    public function getMessage(): string { return $this->message; }
    public function setMessage(string $message): self { $this->message = $message; return $this; }
    public function getContext(): ?string { return $this->context; }
    public function setContext(?string $context): self { $this->context = $context; return $this; }
    public function getMetadata(): ?array { return $this->metadata; }
    public function setMetadata(?array $metadata): self { $this->metadata = $metadata; return $this; }
    public function getSensor(): ?Sensor { return $this->sensor; }
    public function setSensor(?Sensor $sensor): self { $this->sensor = $sensor; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getAcknowledgedAt(): ?\DateTimeImmutable { return $this->acknowledgedAt; }
    public function setAcknowledgedAt(?\DateTimeImmutable $acknowledgedAt): self { $this->acknowledgedAt = $acknowledgedAt; return $this; }
    public function isAcknowledged(): bool { return $this->acknowledgedAt !== null; }
}
