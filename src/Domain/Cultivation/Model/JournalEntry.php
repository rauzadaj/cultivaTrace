<?php

namespace App\Domain\Cultivation\Model;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Domain\Cultivation\Enum\JournalEntryType;
use App\Domain\Cultivation\ValueObject\NutrientConcentration;
use App\Domain\Cultivation\ValueObject\PhLevel;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'journal_entry')]
#[ORM\Index(name: 'idx_journal_entry_occurred_at', columns: ['occurred_at'])]
#[ORM\Index(name: 'idx_journal_entry_crop_occurred_at', columns: ['crop_id', 'occurred_at'])]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(),
    ],
    normalizationContext: ['groups' => ['journal:read']],
    denormalizationContext: ['groups' => ['journal:write']],
    order: ['occurredAt' => 'DESC'],
)]
class JournalEntry
{
    private bool $sealed = false;

    #[ORM\Id]
    #[ORM\Column(length: 26, unique: true)]
    #[Groups(['journal:read'])]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Crop::class, inversedBy: 'journalEntries', fetch: 'EAGER')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    #[Groups(['journal:read', 'journal:write'])]
    private ?Crop $crop = null;

    #[ORM\Column(length: 32, enumType: JournalEntryType::class)]
    #[Assert\NotNull]
    #[Groups(['journal:read', 'journal:write'])]
    private JournalEntryType $type = JournalEntryType::Observation;

    #[ORM\Column(name: 'occurred_at')]
    #[Assert\NotNull]
    #[Groups(['journal:read', 'journal:write'])]
    private ?\DateTimeImmutable $occurredAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 5000)]
    #[Groups(['journal:read', 'journal:write'])]
    private ?string $notes = null;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    #[Groups(['journal:read', 'journal:write'])]
    private array $metadata = [];

    #[ORM\Embedded(class: PhLevel::class, columnPrefix: 'ph_')]
    #[Assert\Valid]
    #[Groups(['journal:read', 'journal:write'])]
    private ?PhLevel $phLevel = null;

    #[ORM\Embedded(class: NutrientConcentration::class, columnPrefix: 'nutrient_')]
    #[Assert\Valid]
    #[Groups(['journal:read', 'journal:write'])]
    private ?NutrientConcentration $nutrientConcentration = null;

    public function __construct()
    {
        $this->id = (string) new Ulid();
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getCrop(): ?Crop
    {
        return $this->crop;
    }

    public function setCrop(Crop $crop): self
    {
        $this->assertMutable();
        $this->crop = $crop;

        return $this;
    }

    public function getType(): JournalEntryType
    {
        return $this->type;
    }

    public function setType(JournalEntryType $type): self
    {
        $this->assertMutable();
        $this->type = $type;

        return $this;
    }

    public function getOccurredAt(): ?\DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function setOccurredAt(\DateTimeImmutable $occurredAt): self
    {
        $this->assertMutable();
        $this->occurredAt = $occurredAt;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->assertMutable();
        $this->notes = null === $notes ? null : trim($notes);

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
        $this->assertMutable();
        $this->metadata = $metadata;

        return $this;
    }

    public function getPhLevel(): ?PhLevel
    {
        return $this->phLevel;
    }

    public function setPhLevel(?PhLevel $phLevel): self
    {
        $this->assertMutable();
        $this->phLevel = $phLevel;

        return $this;
    }

    public function getNutrientConcentration(): ?NutrientConcentration
    {
        return $this->nutrientConcentration;
    }

    public function setNutrientConcentration(?NutrientConcentration $nutrientConcentration): self
    {
        $this->assertMutable();
        $this->nutrientConcentration = $nutrientConcentration;

        return $this;
    }

    public function isSealed(): bool
    {
        return $this->sealed;
    }

    #[ORM\PostPersist]
    #[ORM\PostLoad]
    public function seal(): self
    {
        $this->sealed = true;

        return $this;
    }

    private function assertMutable(): void
    {
        if ($this->sealed) {
            throw \App\Domain\Cultivation\Exception\JournalEntryAppendOnlyViolation::update();
        }
    }
}
