<?php

namespace App\Tests\Doctrine;

use App\Entity\Farm;
use App\Tests\Fixture\TenantIsolationFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TenantFilterTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager = $entityManager;

        $this->resetSchema();
    }

    protected function tearDown(): void
    {
        $filters = $this->entityManager->getFilters();
        if ($filters->isEnabled('tenant_filter')) {
            $filters->disable('tenant_filter');
        }

        $this->entityManager->close();
        unset($this->entityManager);

        parent::tearDown();
    }

    public function testTenantFilterIsolatesDataBetweenOrganizations(): void
    {
        $fixtureSet = TenantIsolationFixtures::load($this->entityManager);

        $filter = $this->entityManager->getFilters()->enable('tenant_filter');
        $filter->setParameter('tenantId', (string) $fixtureSet->organizationA->getId());

        $visibleForUserA = $this->entityManager->getRepository(Farm::class)->findAll();

        self::assertCount(1, $visibleForUserA);
        self::assertSame('Farm A', $visibleForUserA[0]->getName());
        self::assertSame(
            (string) $fixtureSet->organizationA->getId(),
            (string) $visibleForUserA[0]->getTenantId(),
        );
        self::assertNotContains('Farm B', array_map(
            static fn (Farm $farm): string => $farm->getName(),
            $visibleForUserA,
        ));

        $this->entityManager->clear();
        $this->entityManager->getFilters()->disable('tenant_filter');

        $filter = $this->entityManager->getFilters()->enable('tenant_filter');
        $filter->setParameter('tenantId', (string) $fixtureSet->organizationB->getId());

        $visibleForUserB = $this->entityManager->getRepository(Farm::class)->findAll();

        self::assertCount(1, $visibleForUserB);
        self::assertSame('Farm B', $visibleForUserB[0]->getName());
        self::assertSame(
            (string) $fixtureSet->organizationB->getId(),
            (string) $visibleForUserB[0]->getTenantId(),
        );
        self::assertNotContains('Farm A', array_map(
            static fn (Farm $farm): string => $farm->getName(),
            $visibleForUserB,
        ));
    }

    private function resetSchema(): void
    {
        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = [
            $this->entityManager->getClassMetadata(\App\Entity\Organization::class),
            $this->entityManager->getClassMetadata(\App\Entity\User::class),
            $this->entityManager->getClassMetadata(Farm::class),
        ];

        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }
}
