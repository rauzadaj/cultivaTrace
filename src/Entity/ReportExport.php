<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\ReportExportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ReportExportRepository::class)]
#[ORM\Table(name: 'report_export')]
#[ORM\Index(columns: ['tenant_id', 'created_at'], name: 'idx_report_export_tenant_created')]
#[ORM\Index(name: 'IDX_8E164AB7B3C618F7', columns: ['generated_by_id'])]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/reporting/exports',
            security: "is_granted('ROLE_SUPER_ADMIN') or is_granted('ROLE_ORG_ADMIN')"
        ),
        new Get(
            uriTemplate: '/reporting/exports/{id}',
            security: "(is_granted('ROLE_SUPER_ADMIN') or is_granted('ROLE_ORG_ADMIN')) and is_granted('TENANT_ACCESS', object)"
        ),
    ],
    normalizationContext: ['groups' => ['report_export:read']],
    order: ['createdAt' => 'DESC'],
)]
class ReportExport
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['report_export:read'])]
    private Uuid $id;

    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $tenantId;

    #[ORM\Column(length: 64)]
    #[Groups(['report_export:read'])]
    private string $type;

    #[ORM\Column(length: 16)]
    #[Groups(['report_export:read'])]
    private string $format;

    #[ORM\Column(length: 32)]
    #[Groups(['report_export:read'])]
    private string $status = 'ready';

    #[ORM\Column(length: 255)]
    #[Groups(['report_export:read'])]
    private string $fileName;

    #[ORM\Column(length: 500)]
    private string $filePath;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    #[Groups(['report_export:read'])]
    private array $filters = [];

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['jsonb' => true])]
    #[Groups(['report_export:read'])]
    private ?array $summary = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['report_export:read'])]
    private User $generatedBy;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['report_export:read'])]
    private \DateTimeImmutable $createdAt;

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
    public function getFormat(): string { return $this->format; }
    public function setFormat(string $format): self { $this->format = $format; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }
    public function getFileName(): string { return $this->fileName; }
    public function setFileName(string $fileName): self { $this->fileName = $fileName; return $this; }
    public function getFilePath(): string { return $this->filePath; }
    public function setFilePath(string $filePath): self { $this->filePath = $filePath; return $this; }
    /** @return array<string, mixed> */
    public function getFilters(): array { return $this->filters; }
    /** @param array<string, mixed> $filters */
    public function setFilters(array $filters): self { $this->filters = $filters; return $this; }
    /** @return array<string, mixed>|null */
    public function getSummary(): ?array { return $this->summary; }
    /** @param array<string, mixed>|null $summary */
    public function setSummary(?array $summary): self { $this->summary = $summary; return $this; }
    public function getGeneratedBy(): User { return $this->generatedBy; }
    public function setGeneratedBy(User $generatedBy): self { $this->generatedBy = $generatedBy; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
