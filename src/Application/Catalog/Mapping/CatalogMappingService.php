<?php

declare(strict_types=1);

namespace App\Application\Catalog\Mapping;

use App\Domain\Catalog\Model\ExternalCatalogEntry;
use App\Domain\Catalog\Model\GeneticCatalogMapping;
use App\Domain\Cultivation\Model\Genetic;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Drives the dormant {@see GeneticCatalogMapping} layer: it proposes links
 * between ingested supplier records ({@see ExternalCatalogEntry}) and internal
 * validated {@see Genetic} sheets, and lets a reviewer approve or reject them.
 *
 * Proposals are created in the {@see GeneticCatalogMapping::STATUS_PENDING}
 * state and never mutate `Genetic` — the internal records stay authoritative.
 */
final class CatalogMappingService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Scan external catalog entries and create PENDING mappings for those that
     * match an internal genetic by normalized code or name, when no mapping for
     * the pair exists yet. Idempotent: re-running creates no duplicates.
     *
     * @return list<GeneticCatalogMapping> the mappings created during this run
     */
    public function proposeMappings(): array
    {
        $entries = $this->entityManager->getRepository(ExternalCatalogEntry::class)->findAll();
        if ([] === $entries) {
            return [];
        }

        /** @var list<Genetic> $genetics */
        $genetics = $this->entityManager->getRepository(Genetic::class)->findAll();
        if ([] === $genetics) {
            return [];
        }

        $byCode = [];
        $byName = [];
        foreach ($genetics as $genetic) {
            $code = $this->normalize((string) $genetic->getCode());
            if ('' !== $code) {
                $byCode[$code] ??= $genetic;
            }
            $name = $this->normalize((string) $genetic->getName());
            if ('' !== $name) {
                $byName[$name] ??= $genetic;
            }
        }

        $created = [];
        foreach ($entries as $entry) {
            $genetic = $byCode[$this->normalize((string) $entry->getExternalCode())]
                ?? $byName[$this->normalize((string) $entry->getName())]
                ?? null;

            if (!$genetic instanceof Genetic) {
                continue;
            }

            if ($this->mappingExists($entry, $genetic)) {
                continue;
            }

            $mapping = (new GeneticCatalogMapping())
                ->setExternalCatalogEntry($entry)
                ->setGenetic($genetic)
                ->setStatus(GeneticCatalogMapping::STATUS_PENDING);

            $this->entityManager->persist($mapping);
            $created[] = $mapping;
        }

        if ([] !== $created) {
            $this->entityManager->flush();
        }

        return $created;
    }

    /**
     * Approve a pending mapping, recording the reviewer and timestamp.
     *
     * @throws \InvalidArgumentException when the mapping is not pending
     */
    public function approve(GeneticCatalogMapping $mapping, ?User $reviewer = null, ?string $notes = null): GeneticCatalogMapping
    {
        return $this->review($mapping, GeneticCatalogMapping::STATUS_LINKED, $reviewer, $notes);
    }

    /**
     * Reject a pending mapping, recording the reviewer and timestamp.
     *
     * @throws \InvalidArgumentException when the mapping is not pending
     */
    public function reject(GeneticCatalogMapping $mapping, ?User $reviewer = null, ?string $notes = null): GeneticCatalogMapping
    {
        return $this->review($mapping, GeneticCatalogMapping::STATUS_REJECTED, $reviewer, $notes);
    }

    private function review(GeneticCatalogMapping $mapping, string $status, ?User $reviewer, ?string $notes): GeneticCatalogMapping
    {
        if (GeneticCatalogMapping::STATUS_PENDING !== $mapping->getStatus()) {
            throw new \InvalidArgumentException(sprintf(
                'Only pending mappings can be reviewed, got "%s".',
                $mapping->getStatus(),
            ));
        }

        $mapping
            ->setStatus($status)
            ->setReviewedBy($reviewer)
            ->setReviewedAt(new \DateTimeImmutable());

        if (null !== $notes) {
            $mapping->setNotes($notes);
        }

        $this->entityManager->flush();

        return $mapping;
    }

    private function mappingExists(ExternalCatalogEntry $entry, Genetic $genetic): bool
    {
        return null !== $this->entityManager->getRepository(GeneticCatalogMapping::class)->findOneBy([
            'externalCatalogEntry' => $entry,
            'genetic' => $genetic,
        ]);
    }

    private function normalize(string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', strtolower(trim($value))) ?? '';
    }
}
