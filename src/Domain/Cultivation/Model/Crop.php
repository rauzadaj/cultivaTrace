<?php

declare(strict_types=1);

namespace App\Domain\Cultivation\Model;

use App\Domain\Cultivation\Enum\CropStage;
use App\Domain\Cultivation\Enum\JournalEntryType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity]
#[ORM\Table(name: 'crop')]
#[ORM\Index(name: 'idx_crop_stage', columns: ['current_stage'])]
#[ORM\Index(name: 'idx_crop_seeded_at', columns: ['seeded_at'])]
#[ORM\UniqueConstraint(name: 'uniq_crop_batch_code', columns: ['batch_code'])]
#[UniqueEntity(fields: ['batchCode'])]
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

    #[ORM\Column(type: UuidType::NAME, nullable: true)]
    private ?Uuid $tenantId = null;

    #[ORM\Column(name: 'current_stage', length: 32)]
    #[Groups(['crop:read', 'journal:read'])]
    private string $currentStage = CropStage::Seedling->value;

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

    public function getTenantId(): ?Uuid
    {
        return $this->tenantId;
    }

    public function setTenantId(Uuid $tenantId): self
    {
        $this->tenantId = $tenantId;

        foreach ($this->journalEntries as $journalEntry) {
            $journalEntry->setTenantId($tenantId);
        }

        return $this;
    }

    public function setDisplayName(string $displayName): self
    {
        $this->displayName = trim($displayName);

        return $this;
    }

    public function getCurrentStage(): CropStage
    {
        return CropStage::from($this->currentStage);
    }

    public function getCurrentStageValue(): string
    {
        return $this->currentStage;
    }

    public function setCurrentStageValue(string $currentStage): self
    {
        $this->currentStage = CropStage::from($currentStage)->value;

        return $this;
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

            if ($this->tenantId instanceof Uuid) {
                $journalEntry->setTenantId($this->tenantId);
            }
        }

        return $this;
    }

    public function moveToVegetativeStage(): self
    {
        return $this->setCurrentStageValue(CropStage::Veg->value);
    }

    public function moveToFloweringStage(): self
    {
        return $this->setCurrentStageValue(CropStage::Flower->value);
    }

    public function markHarvested(int $finalYieldGrams, ?\DateTimeImmutable $harvestedAt = null): self
    {
        $this->setCurrentStageValue(CropStage::Harvest->value);
        $this->finalYieldGrams = $finalYieldGrams;
        $this->harvestedAt = $harvestedAt ?? new \DateTimeImmutable();

        return $this;
    }

    public function harvest(int $finalYieldGrams, ?\DateTimeImmutable $harvestedAt = null): self
    {
        return $this->markHarvested($finalYieldGrams, $harvestedAt);
    }

    public function recordStageTransition(CropStage $from, CropStage $to, ?\DateTimeImmutable $occurredAt = null): self
    {
        $journalEntry = (new JournalEntry())
            ->setType(JournalEntryType::StageTransition)
            ->setOccurredAt($occurredAt ?? new \DateTimeImmutable())
            ->setNotes(sprintf('Workflow transition from %s to %s.', $from->value, $to->value))
            ->setMetadata([
                'from' => $from->value,
                'to' => $to->value,
                'source' => 'workflow',
            ]);

        $this->addJournalEntry($journalEntry);

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
