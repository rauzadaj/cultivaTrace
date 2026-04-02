<?php

namespace App\Domain\Cultivation\Model;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'genetic')]
#[ORM\UniqueConstraint(name: 'uniq_genetic_code', columns: ['code'])]
#[UniqueEntity(fields: ['code'])]
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
    normalizationContext: ['groups' => ['genetic:read']],
    denormalizationContext: ['groups' => ['genetic:write']],
)]
class Genetic
{
    #[ORM\Id]
    #[ORM\Column(length: 26, unique: true)]
    #[Groups(['genetic:read', 'crop:read', 'analytics:read'])]
    private string $id;

    #[ORM\Column(length: 64)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    #[Groups(['genetic:read', 'genetic:write', 'crop:read', 'analytics:read'])]
    private ?string $code = null;

    #[ORM\Column(length: 160)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 160)]
    #[Groups(['genetic:read', 'genetic:write', 'crop:read', 'analytics:read'])]
    private ?string $name = null;

    #[ORM\Column(length: 160, nullable: true)]
    #[Assert\Length(max: 160)]
    #[Groups(['genetic:read', 'genetic:write'])]
    private ?string $vendor = null;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    #[Groups(['genetic:read', 'genetic:write'])]
    private array $metadata = [];

    /** @var Collection<int, Crop> */
    #[ORM\OneToMany(mappedBy: 'genetic', targetEntity: Crop::class)]
    private Collection $crops;

    public function __construct()
    {
        $this->id = (string) new Ulid();
        $this->crops = new ArrayCollection();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = trim($code);

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = trim($name);

        return $this;
    }

    public function getVendor(): ?string
    {
        return $this->vendor;
    }

    public function setVendor(?string $vendor): self
    {
        $this->vendor = null === $vendor ? null : trim($vendor);

        return $this;
    }

    /** @return array<string, mixed> */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /** @param array<string, mixed> $metadata */
    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    /** @return Collection<int, Crop> */
    public function getCrops(): Collection
    {
        return $this->crops;
    }

    public function addCrop(Crop $crop): self
    {
        if (!$this->crops->contains($crop)) {
            $this->crops->add($crop);
            $crop->setGenetic($this);
        }

        return $this;
    }
}
