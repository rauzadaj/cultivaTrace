<?php

namespace App\Tests\Doctrine;

use App\Entity\Farm;
use App\Entity\Organization;
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
        $organizationA = (new Organization())
            ->setName('Org A')
            ->setCountry('FR');
        $organizationB = (new Organization())
            ->setName('Org B')
            ->setCountry('DE');

        $this->entityManager->persist($organizationA);
        $this->entityManager->persist($organizationB);
        $this->entityManager->flush();

        $farmA = (new Farm())
            ->setOrganization($organizationA)
            ->setTenantId($organizationA->getId())
            ->setName('Farm A');
        $farmB = (new Farm())
            ->setOrganization($organizationB)
            ->setTenantId($organizationB->getId())
            ->setName('Farm B');

        $this->entityManager->persist($farmA);
        $this->entityManager->persist($farmB);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $filter = $this->entityManager->getFilters()->enable('tenant_filter');
        $filter->setParameter('tenantId', (string) $organizationA->getId());

        $visibleFarms = $this->entityManager->getRepository(Farm::class)->findAll();

        self::assertCount(1, $visibleFarms);
        self::assertSame('Farm A', $visibleFarms[0]->getName());
        self::assertSame(
            (string) $organizationA->getId(),
            (string) $visibleFarms[0]->getTenantId(),
        );
        self::assertNotContains('Farm B', array_map(
            static fn (Farm $farm): string => $farm->getName(),
            $visibleFarms,
        ));
    }

    private function resetSchema(): void
    {
        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = [
            $this->entityManager->getClassMetadata(Organization::class),
            $this->entityManager->getClassMetadata(Farm::class),
        ];

        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }
}
