<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Model;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Domain\Cultivation\Model\Genetic;
use App\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'genetic_catalog_mapping')]
#[ORM\Index(name: 'IDX_67A16411F34034BC', columns: ['external_catalog_entry_id'])]
#[ORM\Index(name: 'IDX_67A16411A59B8B13', columns: ['genetic_id'])]
#[ORM\Index(name: 'IDX_67A16411D6A86055', columns: ['reviewed_by_id'])]
#[ORM\UniqueConstraint(name: 'uniq_genetic_catalog_mapping', columns: ['external_catalog_entry_id', 'genetic_id'])]
#[ApiResource(
    operations: [
        new GetCollection(
            security: "is_granted('ROLE_SUPER_ADMIN') or is_granted('ROLE_ORG_ADMIN') or is_granted('ROLE_ORG_USER') or is_granted('ROLE_API')"
        ),
        new Get(
            security: "is_granted('ROLE_SUPER_ADMIN') or is_granted('ROLE_ORG_ADMIN') or is_granted('ROLE_ORG_USER') or is_granted('ROLE_API')"
        ),
        new Post(security: "is_granted('ROLE_SUPER_ADMIN')"),
        new Patch(security: "is_granted('ROLE_SUPER_ADMIN')"),
        new Delete(security: "is_granted('ROLE_SUPER_ADMIN')"),
    ],
    normalizationContext: ['groups' => ['genetic_mapping:read']],
    denormalizationContext: ['groups' => ['genetic_mapping:write']],
)]
class GeneticCatalogMapping
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_LINKED = 'linked';
    public const STATUS_REJECTED = 'rejected';

    #[ORM\Id]
    #[ORM\Column(length: 26, unique: true)]
    #[Groups(['genetic_mapping:read'])]
    private string $id;

    #[ORM\ManyToOne(targetEntity: ExternalCatalogEntry::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['genetic_mapping:read', 'genetic_mapping:write'])]
    #[Assert\NotNull]
    private ?ExternalCatalogEntry $externalCatalogEntry = null;

    #[ORM\ManyToOne(targetEntity: Genetic::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['genetic_mapping:read', 'genetic_mapping:write'])]
    #[Assert\NotNull]
    private ?Genetic $genetic = null;

    #[ORM\Column(length: 32)]
    #[Assert\Choice(choices: [self::STATUS_PENDING, self::STATUS_LINKED, self::STATUS_REJECTED])]
    #[Groups(['genetic_mapping:read', 'genetic_mapping:write'])]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['genetic_mapping:read', 'genetic_mapping:write'])]
    private ?string $notes = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['genetic_mapping:read', 'genetic_mapping:write'])]
    private ?User $reviewedBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['genetic_mapping:read', 'genetic_mapping:write'])]
    private ?\DateTimeImmutable $reviewedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, name: 'created_at')]
    #[Groups(['genetic_mapping:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, name: 'updated_at')]
    #[Groups(['genetic_mapping:read'])]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id = (string) new Ulid();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getExternalCatalogEntry(): ?ExternalCatalogEntry
    {
        return $this->externalCatalogEntry;
    }

    public function setExternalCatalogEntry(ExternalCatalogEntry $externalCatalogEntry): self
    {
        $this->externalCatalogEntry = $externalCatalogEntry;
        $this->touch();

        return $this;
    }

    public function getGenetic(): ?Genetic
    {
        return $this->genetic;
    }

    public function setGenetic(Genetic $genetic): self
    {
        $this->genetic = $genetic;
        $this->touch();

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        $this->touch();

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = null === $notes ? null : trim($notes);
        $this->touch();

        return $this;
    }

    public function getReviewedBy(): ?User
    {
        return $this->reviewedBy;
    }

    public function setReviewedBy(?User $reviewedBy): self
    {
        $this->reviewedBy = $reviewedBy;
        $this->touch();

        return $this;
    }

    public function getReviewedAt(): ?\DateTimeImmutable
    {
        return $this->reviewedAt;
    }

    public function setReviewedAt(?\DateTimeImmutable $reviewedAt): self
    {
        $this->reviewedAt = $reviewedAt;
        $this->touch();

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
