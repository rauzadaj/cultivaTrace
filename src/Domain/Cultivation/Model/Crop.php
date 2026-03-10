<?php

namespace App\Domain\Cultivation\Model;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Domain\Cultivation\Enum\CropStage;
use App\Domain\Cultivation\Exception\InvalidCropStageTransition;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity]
#[ORM\Table(name: 'crop')]
#[ORM\Index(name: 'idx_crop_stage', columns: ['current_stage'])]
#[ORM\Index(name: 'idx_crop_seeded_at', columns: ['seeded_at'])]
#[ORM\UniqueConstraint(name: 'uniq_crop_batch_code', columns: ['batch_code'])]
#[UniqueEntity(fields: ['batchCode'])]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(),
        new Patch(),
    ],
    normalizationContext: ['groups' => ['crop:read']],
    denormalizationContext: ['groups' => ['crop:write']],
    order: ['seededAt' => 'DESC'],
)]
#[Assert\Callback('validateChronology')]
class Crop
{
    #[ORM\Id]
    #[ORM\Column(length: 26, unique: true)]
    #[Groups(['crop:read', 'journal:read'])]
    private string $id;

    #[ORM\Column(name: 'batch_code', length: 64)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    #[Groups(['crop:read', 'crop:write', 'journal:read'])]
    private ?string $batchCode = null;

    #[ORM\Column(name: 'display_name', length: 160)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 160)]
    #[Groups(['crop:read', 'crop:write', 'journal:read'])]
    private ?string $displayName = null;

    #[ORM\Column(name: 'current_stage', length: 32, enumType: CropStage::class)]
    #[Groups(['crop:read', 'journal:read'])]
    private CropStage $currentStage = CropStage::Seedling;

    #[ORM\Column(name: 'seeded_at')]
    #[Assert\NotNull]
    #[Groups(['crop:read', 'crop:write'])]
    private ?\DateTimeImmutable $seededAt = null;

    #[ORM\Column(name: 'harvested_at', nullable: true)]
    #[Groups(['crop:read'])]
    private ?\DateTimeImmutable $harvestedAt = null;

    #[ORM\Column(name: 'final_yield_grams', type: 'integer', nullable: true)]
    #[Assert\PositiveOrZero]
    #[Groups(['crop:read'])]
    private ?int $finalYieldGrams = null;

    #[ORM\ManyToOne(targetEntity: Genetic::class, inversedBy: 'crops', fetch: 'EAGER')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    #[Groups(['crop:read', 'crop:write'])]
    private ?Genetic $genetic = null;

    /** @var Collection<int, JournalEntry> */
    #[ORM\OneToMany(mappedBy: 'crop', targetEntity: JournalEntry::class, cascade: ['persist'], orphanRemoval: false)]
    #[ORM\OrderBy(['occurredAt' => 'DESC'])]
    private Collection $journalEntries;

    public function __construct()
    {
        $this->id = (string) new Ulid();
        $this->seededAt = new \DateTimeImmutable();
        $this->journalEntries = new ArrayCollection();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getBatchCode(): ?string
    {
        return $this->batchCode;
    }

    public function setBatchCode(string $batchCode): self
    {
        $this->batchCode = trim($batchCode);

        return $this;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): self
    {
        $this->displayName = trim($displayName);

        return $this;
    }

    public function getCurrentStage(): CropStage
    {
        return $this->currentStage;
    }

    public function getSeededAt(): ?\DateTimeImmutable
    {
        return $this->seededAt;
    }

    public function setSeededAt(\DateTimeImmutable $seededAt): self
    {
        $this->seededAt = $seededAt;

        return $this;
    }

    public function getHarvestedAt(): ?\DateTimeImmutable
    {
        return $this->harvestedAt;
    }

    public function getFinalYieldGrams(): ?int
    {
        return $this->finalYieldGrams;
    }

    public function getGenetic(): ?Genetic
    {
        return $this->genetic;
    }

    public function setGenetic(Genetic $genetic): self
    {
        $this->genetic = $genetic;

        return $this;
    }

    /** @return Collection<int, JournalEntry> */
    public function getJournalEntries(): Collection
    {
        return $this->journalEntries;
    }

    public function addJournalEntry(JournalEntry $journalEntry): self
    {
        if (!$this->journalEntries->contains($journalEntry)) {
            $this->journalEntries->add($journalEntry);
            $journalEntry->setCrop($this);
        }

        return $this;
    }

    public function moveToVegetativeStage(): self
    {
        return $this->transitionTo(CropStage::Veg);
    }

    public function moveToFloweringStage(): self
    {
        return $this->transitionTo(CropStage::Flower);
    }

    public function harvest(int $finalYieldGrams, ?\DateTimeImmutable $harvestedAt = null): self
    {
        $this->transitionTo(CropStage::Harvest);
        $this->finalYieldGrams = $finalYieldGrams;
        $this->harvestedAt = $harvestedAt ?? new \DateTimeImmutable();

        return $this;
    }

    public function transitionTo(CropStage $targetStage): self
    {
        $allowedTransitions = [
            CropStage::Seedling->value => CropStage::Veg,
            CropStage::Veg->value => CropStage::Flower,
            CropStage::Flower->value => CropStage::Harvest,
            CropStage::Harvest->value => CropStage::Harvest,
        ];

        $expectedTarget = $allowedTransitions[$this->currentStage->value];

        if ($targetStage !== $expectedTarget) {
            throw InvalidCropStageTransition::between($this->currentStage, $targetStage);
        }

        $this->currentStage = $targetStage;

        if (CropStage::Harvest !== $targetStage) {
            $this->harvestedAt = null;
            $this->finalYieldGrams = null;
        }

        return $this;
    }

    public function validateChronology(ExecutionContextInterface $context): void
    {
        if (null === $this->seededAt || null === $this->harvestedAt) {
            return;
        }

        if ($this->harvestedAt < $this->seededAt) {
            $context->buildViolation('Harvest date must be greater than or equal to seed date.')
                ->atPath('harvestedAt')
                ->addViolation();
        }
    }
}
