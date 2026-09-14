<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use App\State\SensorStateProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Sensor — capteur IoT associé à une salle.
 *
 * Protocoles supportés :
 *   - mqtt : le capteur publie sur le topic cannas/{tenantId}/{sensorId}/reading
 *   - rest : le capteur envoie des requêtes HTTP POST vers /api/sensors/{id}/reading
 *   - simulated : données générées par le script scripts/simulate_sensors.py
 *
 * Les SensorReadings sont stockées dans une table TimescaleDB séparée
 * (pas une entité Doctrine classique — requêtes via DBAL natif pour les perfs).
 *
 * Thresholds (seuils d'alerte) format JSON :
 * {
 *   "min": 18.0,
 *   "max": 28.0,
 *   "unit": "°C",
 *   "alertCooldownMinutes": 60
 * }
 */
#[ORM\Entity]
#[ORM\Table(name: 'sensor')]
#[ApiResource(operations: [
    new GetCollection(
        security: "is_granted('ROLE_SUPER_ADMIN') or is_granted('ROLE_ORG_ADMIN') or is_granted('ROLE_ORG_USER') or is_granted('ROLE_API')"
    ),
    new Get(
        security: "(is_granted('ROLE_SUPER_ADMIN') or is_granted('ROLE_ORG_ADMIN') or is_granted('ROLE_ORG_USER') or is_granted('ROLE_API')) and is_granted('TENANT_ACCESS', object)"
    ),
    new Post(
        processor: SensorStateProcessor::class,
        security: "is_granted('ROLE_SUPER_ADMIN') or is_granted('ROLE_ORG_ADMIN') or is_granted('ROLE_API')"
    ),
    new Patch(
        processor: SensorStateProcessor::class,
        security: "(is_granted('ROLE_SUPER_ADMIN') or is_granted('ROLE_ORG_ADMIN') or is_granted('ROLE_API')) and is_granted('TENANT_ACCESS', object)",
        securityPostDenormalize: "is_granted('ROLE_SUPER_ADMIN') or is_granted('TENANT_ACCESS', object)"
    ),
    new Delete(
        processor: SensorStateProcessor::class,
        security: "(is_granted('ROLE_SUPER_ADMIN') or is_granted('ROLE_ORG_ADMIN')) and is_granted('TENANT_ACCESS', object)"
    ),
])]
#[ApiFilter(SearchFilter::class, properties: [
    'room.id' => 'exact',
    'type'    => 'exact',
    'status'  => 'exact',
])]
class Sensor
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private Uuid $id;

    #[ORM\Column(type: UuidType::NAME)]
    #[ApiProperty(readable: false, writable: false)]
    private Uuid $tenantId;

    #[ORM\ManyToOne(targetEntity: Room::class, inversedBy: 'sensors')]
    #[ORM\JoinColumn(nullable: false)]
    private Room $room;

    /**
     * temperature | humidity | co2 | ph | ec | vpd
     */
    #[ORM\Column(length: 50)]
    #[Assert\Choice(choices: ['temperature', 'humidity', 'co2', 'ph', 'ec', 'vpd'])]
    private string $type;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $deviceId;

    /**
     * mqtt | rest | simulated
     */
    #[ORM\Column(length: 50)]
    #[Assert\Choice(choices: ['mqtt', 'rest', 'simulated'])]
    private string $protocol = 'simulated';

    /**
     * online | offline | warning
     */
    #[ORM\Column(length: 50)]
    private string $status = 'offline';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastSeen = null;

    /**
     * Seuils d'alerte — voir format dans le docblock de la classe
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $thresholds = null;

    public function __construct()
    {
        $this->id = Uuid::v4();
    }

    public function isOnline(): bool
    {
        if ($this->lastSeen === null) return false;
        return $this->lastSeen > new \DateTimeImmutable('-5 minutes');
    }

    public function isValueOutOfRange(float $value): bool
    {
        if ($this->thresholds === null) return false;
        $min = $this->thresholds['min'] ?? null;
        $max = $this->thresholds['max'] ?? null;
        if ($min !== null && $value < $min) return true;
        if ($max !== null && $value > $max) return true;
        return false;
    }

    public function getId(): Uuid { return $this->id; }
    public function getTenantId(): Uuid { return $this->tenantId; }
    public function setTenantId(Uuid $id): self { $this->tenantId = $id; return $this; }
    public function getRoom(): Room { return $this->room; }
    public function setRoom(Room $room): self { $this->room = $room; return $this; }
    public function getType(): string { return $this->type; }
    public function setType(string $t): self { $this->type = $t; return $this; }
    public function getDeviceId(): string { return $this->deviceId; }
    public function setDeviceId(string $d): self { $this->deviceId = $d; return $this; }
    public function getProtocol(): string { return $this->protocol; }
    public function setProtocol(string $p): self { $this->protocol = $p; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $s): self { $this->status = $s; return $this; }
    public function getLastSeen(): ?\DateTimeImmutable { return $this->lastSeen; }
    public function setLastSeen(?\DateTimeImmutable $d): self { $this->lastSeen = $d; return $this; }
    public function getThresholds(): ?array { return $this->thresholds; }
    public function setThresholds(?array $t): self { $this->thresholds = $t; return $this; }
}
