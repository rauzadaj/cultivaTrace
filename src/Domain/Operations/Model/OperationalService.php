<?php

namespace App\Domain\Operations\Model;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'operational_service')]
#[ORM\UniqueConstraint(name: 'uniq_operational_service_name', columns: ['name'])]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('IS_AUTHENTICATED_FULLY')"),
        new Get(security: "is_granted('IS_AUTHENTICATED_FULLY')"),
        new Post(security: "is_granted('ROLE_ADMIN')"),
        new Patch(security: "is_granted('ROLE_ADMIN')"),
        new Delete(security: "is_granted('ROLE_ADMIN')"),
    ],
    normalizationContext: ['groups' => ['service:read']],
    denormalizationContext: ['groups' => ['service:write']],
    order: ['position' => 'ASC', 'name' => 'ASC'],
)]
class OperationalService
{
    #[ORM\Id]
    #[ORM\Column(length: 26, unique: true)]
    #[Groups(['service:read'])]
    private string $id;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    #[Groups(['service:read', 'service:write'])]
    private ?string $name = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    #[Groups(['service:read', 'service:write'])]
    private ?string $category = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['service:read', 'service:write'])]
    private ?string $description = null;

    #[ORM\Column(length: 64)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    #[Groups(['service:read', 'service:write'])]
    private ?string $icon = null;

    #[ORM\Column(length: 32)]
    #[Assert\Choice(choices: ['primary', 'warning', 'success'])]
    #[Groups(['service:read', 'service:write'])]
    private string $tone = 'primary';

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 80)]
    #[Groups(['service:read', 'service:write'])]
    private ?string $statusLabel = null;

    #[ORM\Column(type: 'integer')]
    #[Assert\PositiveOrZero]
    #[Groups(['service:read', 'service:write'])]
    private int $position = 0;

    #[ORM\Column(name: 'updated_at')]
    #[Groups(['service:read'])]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id = (string) new Ulid();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
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

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(string $category): self
    {
        $this->category = trim($category);
        $this->touch();

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = trim($description);
        $this->touch();

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(string $icon): self
    {
        $this->icon = trim($icon);
        $this->touch();

        return $this;
    }

    public function getTone(): string
    {
        return $this->tone;
    }

    public function setTone(string $tone): self
    {
        $this->tone = $tone;
        $this->touch();

        return $this;
    }

    public function getStatusLabel(): ?string
    {
        return $this->statusLabel;
    }

    public function setStatusLabel(string $statusLabel): self
    {
        $this->statusLabel = trim($statusLabel);
        $this->touch();

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;
        $this->touch();

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
