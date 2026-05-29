<?php

declare(strict_types=1);

namespace App\Tests\Application\Catalog\Mapping;

use App\Application\Catalog\Mapping\CatalogMappingService;
use App\Domain\Catalog\Model\ExternalCatalogEntry;
use App\Domain\Catalog\Model\GeneticCatalogMapping;
use App\Domain\Cultivation\Model\Genetic;
use App\Entity\Organization;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CatalogMappingServiceTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private CatalogMappingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();
        $container = static::getContainer();
        $this->entityManager = $container->get('doctrine')->getManager();
        $this->service = $container->get(CatalogMappingService::class);

        // GeneticCatalogMapping has a FK to "user" (reviewedBy) and User has a FK
        // to organization; PostgreSQL (unlike SQLite) refuses to create the
        // constraints unless the referenced tables exist, so include both.
        $this->resetSchema([
            Organization::class,
            User::class,
            Genetic::class,
            ExternalCatalogEntry::class,
            GeneticCatalogMapping::class,
        ]);
    }

    protected function tearDown(): void
    {
        $this->entityManager->close();
        parent::tearDown();
    }

    public function testProposeMappingsLinksByCodeAndNameAndIgnoresUnmatched(): void
    {
        $byCode = $this->genetic('HSC_ALPINE', 'Alpine');
        $byName = $this->genetic('INT-777', 'Sunset Sherbet');
        $this->genetic('INT-000', 'Unmatched Internal');

        $matchCode = $this->external('HSC_ALPINE', 'Totally Different Name');
        $matchName = $this->external('SUPPLIER-1', 'sunset  sherbet');
        $this->external('SUPPLIER-2', 'No Match Whatsoever');

        $this->entityManager->flush();

        $created = $this->service->proposeMappings();

        self::assertCount(2, $created);
        foreach ($created as $mapping) {
            self::assertSame(GeneticCatalogMapping::STATUS_PENDING, $mapping->getStatus());
        }

        $persisted = $this->entityManager->getRepository(GeneticCatalogMapping::class)->findAll();
        self::assertCount(2, $persisted);

        $links = [];
        foreach ($persisted as $mapping) {
            $links[$mapping->getExternalCatalogEntry()?->getId()] = $mapping->getGenetic()?->getId();
        }
        self::assertSame($byCode->getId(), $links[$matchCode->getId()] ?? null);
        self::assertSame($byName->getId(), $links[$matchName->getId()] ?? null);
    }

    public function testProposeMappingsIsIdempotent(): void
    {
        $this->genetic('HSC_ALPINE', 'Alpine');
        $this->external('HSC_ALPINE', 'Alpine');
        $this->entityManager->flush();

        $first = $this->service->proposeMappings();
        $second = $this->service->proposeMappings();

        self::assertCount(1, $first);
        self::assertCount(0, $second);
        self::assertCount(1, $this->entityManager->getRepository(GeneticCatalogMapping::class)->findAll());
    }

    public function testProposeMappingsNeverMutatesGenetic(): void
    {
        $genetic = $this->genetic('HSC_ALPINE', 'Alpine');
        $genetic->setMetadata(['authoritative' => true]);
        $this->external('HSC_ALPINE', 'Supplier Alpine');
        $this->entityManager->flush();

        $this->service->proposeMappings();
        $this->entityManager->clear();

        $reloaded = $this->entityManager->getRepository(Genetic::class)->find($genetic->getId());
        self::assertInstanceOf(Genetic::class, $reloaded);
        self::assertSame('Alpine', $reloaded->getName());
        self::assertSame(['authoritative' => true], $reloaded->getMetadata());
    }

    public function testApproveTransitionsPendingToLinkedWithReviewMetadata(): void
    {
        $this->genetic('HSC_ALPINE', 'Alpine');
        $this->external('HSC_ALPINE', 'Alpine');
        $this->entityManager->flush();

        [$mapping] = $this->service->proposeMappings();

        $this->service->approve($mapping, null, 'Verified against lab sheet.');

        self::assertSame(GeneticCatalogMapping::STATUS_LINKED, $mapping->getStatus());
        self::assertNotNull($mapping->getReviewedAt());
        self::assertSame('Verified against lab sheet.', $mapping->getNotes());
    }

    public function testRejectTransitionsPendingToRejected(): void
    {
        $this->genetic('HSC_ALPINE', 'Alpine');
        $this->external('HSC_ALPINE', 'Alpine');
        $this->entityManager->flush();

        [$mapping] = $this->service->proposeMappings();

        $this->service->reject($mapping);

        self::assertSame(GeneticCatalogMapping::STATUS_REJECTED, $mapping->getStatus());
        self::assertNotNull($mapping->getReviewedAt());
    }

    public function testReviewingANonPendingMappingThrows(): void
    {
        $this->genetic('HSC_ALPINE', 'Alpine');
        $this->external('HSC_ALPINE', 'Alpine');
        $this->entityManager->flush();

        [$mapping] = $this->service->proposeMappings();
        $this->service->approve($mapping);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->reject($mapping);
    }

    private function genetic(string $code, string $name): Genetic
    {
        $genetic = (new Genetic())
            ->setCode($code)
            ->setName($name);
        $this->entityManager->persist($genetic);

        return $genetic;
    }

    private function external(string $code, string $name): ExternalCatalogEntry
    {
        $entry = (new ExternalCatalogEntry())
            ->setSourceProvider('test-provider')
            ->setExternalCode($code)
            ->setName($name)
            ->setGenetics('Parent A x Parent B')
            ->setImageUrl('https://example.test/image.jpg')
            ->setSourceUrl('https://example.test/source');
        $this->entityManager->persist($entry);

        return $entry;
    }

    /**
     * @param list<class-string> $classes
     */
    private function resetSchema(array $classes): void
    {
        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = array_map(
            fn (string $class): \Doctrine\ORM\Mapping\ClassMetadata => $this->entityManager->getClassMetadata($class),
            $classes,
        );

        $platformClass = $this->entityManager->getConnection()->getDatabasePlatform()::class;
        if (str_contains($platformClass, 'PostgreSQL')) {
            $this->entityManager->getConnection()->executeStatement('DROP SCHEMA IF EXISTS public CASCADE');
            $this->entityManager->getConnection()->executeStatement('CREATE SCHEMA public');
            $this->entityManager->getConnection()->executeStatement('SET search_path TO public');
        } else {
            $schemaTool->dropSchema($metadata);
        }

        $schemaTool->createSchema($metadata);
    }
}
