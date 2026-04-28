<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Model;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'external_catalog_entry')]
#[ORM\Index(name: 'idx_external_catalog_provider', columns: ['source_provider'])]
#[ORM\UniqueConstraint(name: 'uniq_external_catalog_provider_code', columns: ['source_provider', 'external_code'])]
#[ApiResource(
    operations: [
        new GetCollection(
            security: "is_granted('ROLE_SUPER_ADMIN') or is_granted('ROLE_ORG_ADMIN') or is_granted('ROLE_ORG_USER') or is_granted('ROLE_API')"
        ),
        new Get(
            security: "is_granted('ROLE_SUPER_ADMIN') or is_granted('ROLE_ORG_ADMIN') or is_granted('ROLE_ORG_USER') or is_granted('ROLE_API')"
        ),
    ],
    normalizationContext: ['groups' => ['external_catalog:read']],
)]
#[ApiFilter(SearchFilter::class, properties: [
    'sourceProvider' => 'exact',
    'externalCode' => 'exact',
    'name' => 'partial',
    'vendor' => 'partial',
])]
class ExternalCatalogEntry
{
    #[ORM\Id]
    #[ORM\Column(length: 26, unique: true)]
    #[Groups(['external_catalog:read', 'genetic_mapping:read'])]
    private string $id;

    #[ORM\Column(length: 120, name: 'source_provider')]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    #[Groups(['external_catalog:read', 'genetic_mapping:read'])]
    private ?string $sourceProvider = null;

    #[ORM\Column(length: 64, name: 'external_code')]
    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    #[Groups(['external_catalog:read', 'genetic_mapping:read'])]
    private ?string $externalCode = null;

    #[ORM\Column(length: 160)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 160)]
    #[Groups(['external_catalog:read', 'genetic_mapping:read'])]
    private ?string $name = null;

    #[ORM\Column(length: 160, nullable: true)]
    #[Assert\Length(max: 160)]
    #[Groups(['external_catalog:read'])]
    private ?string $vendor = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['external_catalog:read'])]
    private ?string $genetics = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['external_catalog:read'])]
    private string $description = '';

    #[ORM\Column(length: 500, name: 'image_url')]
    #[Assert\NotBlank]
    #[Assert\Length(max: 500)]
    #[Groups(['external_catalog:read'])]
    private ?string $imageUrl = null;

    #[ORM\Column(length: 500, name: 'source_url')]
    #[Assert\NotBlank]
    #[Assert\Length(max: 500)]
    #[Groups(['external_catalog:read'])]
    private ?string $sourceUrl = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, name: 'source_modified_at')]
    #[Groups(['external_catalog:read'])]
    private ?\DateTimeImmutable $sourceModifiedAt = null;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    #[Groups(['external_catalog:read'])]
    private array $markets = [];

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true], name: 'raw_metadata')]
    #[Groups(['external_catalog:read'])]
    private array $rawMetadata = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, name: 'ingested_at')]
    #[Groups(['external_catalog:read'])]
    private \DateTimeImmutable $ingestedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, name: 'updated_at')]
    #[Groups(['external_catalog:read'])]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id = (string) new Ulid();
        $this->ingestedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getSourceProvider(): ?string
    {
        return $this->sourceProvider;
    }

    public function setSourceProvider(string $sourceProvider): self
    {
        $this->sourceProvider = trim($sourceProvider);
        $this->touch();

        return $this;
    }

    public function getExternalCode(): ?string
    {
        return $this->externalCode;
    }

    public function setExternalCode(string $externalCode): self
    {
        $this->externalCode = trim($externalCode);
        $this->touch();

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = trim($name);
        $this->touch();

        return $this;
    }

    public function getVendor(): ?string
    {
        return $this->vendor;
    }

    public function setVendor(?string $vendor): self
    {
        $this->vendor = null === $vendor ? null : trim($vendor);
        $this->touch();

        return $this;
    }

    public function getGenetics(): ?string
    {
        return $this->genetics;
    }

    public function setGenetics(string $genetics): self
    {
        $this->genetics = trim($genetics);
        $this->touch();

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = trim($description);
        $this->touch();

        return $this;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(string $imageUrl): self
    {
        $this->imageUrl = trim($imageUrl);
        $this->touch();

        return $this;
    }

    public function getSourceUrl(): ?string
    {
        return $this->sourceUrl;
    }

    public function setSourceUrl(string $sourceUrl): self
    {
        $this->sourceUrl = trim($sourceUrl);
        $this->touch();

        return $this;
    }

    public function getSourceModifiedAt(): ?\DateTimeImmutable
    {
        return $this->sourceModifiedAt;
    }

    public function setSourceModifiedAt(?\DateTimeImmutable $sourceModifiedAt): self
    {
        $this->sourceModifiedAt = $sourceModifiedAt;
        $this->touch();

        return $this;
    }

    /** @return list<string> */
    public function getMarkets(): array
    {
        return $this->markets;
    }

    /** @param list<string> $markets */
    public function setMarkets(array $markets): self
    {
        $this->markets = $markets;
        $this->touch();

        return $this;
    }

    /** @return array<string, mixed> */
    public function getRawMetadata(): array
    {
        return $this->rawMetadata;
    }

    /** @param array<string, mixed> $rawMetadata */
    public function setRawMetadata(array $rawMetadata): self
    {
        $this->rawMetadata = $rawMetadata;
        $this->touch();

        return $this;
    }

    public function getIngestedAt(): \DateTimeImmutable
    {
        return $this->ingestedAt;
    }

    public function setIngestedAt(\DateTimeImmutable $ingestedAt): self
    {
        $this->ingestedAt = $ingestedAt;

        return $this;
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
