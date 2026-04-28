<?php

declare(strict_types=1);

namespace App\Tests\Doctrine;

use App\Entity\Farm;
use App\Enum\LicenseStatus;
use App\EventListener\TenantListener;
use App\Tests\Fixture\TenantIsolationFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class TenantListenerTest extends KernelTestCase
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
        /** @var TokenStorageInterface $tokenStorage */
        $tokenStorage = static::getContainer()->get(TokenStorageInterface::class);
        $tokenStorage->setToken(null);

        $filters = $this->entityManager->getFilters();
        if ($filters->isEnabled('tenant_filter')) {
            $filters->disable('tenant_filter');
        }

        $this->entityManager->close();
        unset($this->entityManager);

        parent::tearDown();
    }

    public function testTenantListenerRestrictsQueriesToAuthenticatedUserOrganization(): void
    {
        $fixtureSet = TenantIsolationFixtures::load($this->entityManager);

        /** @var TokenStorageInterface $tokenStorage */
        $tokenStorage = static::getContainer()->get(TokenStorageInterface::class);
        $tokenStorage->setToken(new UsernamePasswordToken(
            $fixtureSet->userA,
            'api',
            $fixtureSet->userA->getRoles(),
        ));

        /** @var TenantListener $listener */
        $listener = static::getContainer()->get(TenantListener::class);
        $listener->onKernelRequest(new RequestEvent(
            static::getContainer()->get('kernel'),
            new Request(),
            \Symfony\Component\HttpKernel\HttpKernelInterface::MAIN_REQUEST,
        ));

        $visibleFarms = $this->entityManager->getRepository(Farm::class)->findAll();

        self::assertCount(1, $visibleFarms);
        self::assertSame('Farm A', $visibleFarms[0]->getName());
        self::assertSame(
            (string) $fixtureSet->organizationA->getId(),
            (string) $visibleFarms[0]->getTenantId(),
        );
    }

    public function testTenantListenerKeepsTenantFilterEnabledWhenOrganizationIsSuspended(): void
    {
        $fixtureSet = TenantIsolationFixtures::load($this->entityManager);
        $fixtureSet->organizationA->setLicenseStatus(LicenseStatus::SUSPENDED);
        $this->entityManager->flush();

        /** @var TokenStorageInterface $tokenStorage */
        $tokenStorage = static::getContainer()->get(TokenStorageInterface::class);
        $tokenStorage->setToken(new UsernamePasswordToken(
            $fixtureSet->userA,
            'api',
            $fixtureSet->userA->getRoles(),
        ));

        /** @var TenantListener $listener */
        $listener = static::getContainer()->get(TenantListener::class);

        try {
            $listener->onKernelRequest(new RequestEvent(
                static::getContainer()->get('kernel'),
                new Request(),
                \Symfony\Component\HttpKernel\HttpKernelInterface::MAIN_REQUEST,
            ));
            self::fail('Expected suspended organization access to be denied.');
        } catch (AccessDeniedHttpException) {
            // Expected: access is denied, but the tenant filter must stay active.
        }

        $visibleFarms = $this->entityManager->getRepository(Farm::class)->findAll();

        self::assertCount(1, $visibleFarms);
        self::assertSame('Farm A', $visibleFarms[0]->getName());
    }

    private function resetSchema(): void
    {
        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = [
            $this->entityManager->getClassMetadata(\App\Entity\Organization::class),
            $this->entityManager->getClassMetadata(\App\Entity\User::class),
            $this->entityManager->getClassMetadata(Farm::class),
        ];

        $platformClass = $this->entityManager->getConnection()->getDatabasePlatform()::class;
        if (str_contains($platformClass, 'PostgreSQL')) {
            $this->entityManager->getConnection()->executeStatement('DROP TABLE IF EXISTS farm, "user", organization CASCADE');
        } else {
            $schemaTool->dropSchema($metadata);
        }

        $schemaTool->createSchema($metadata);
    }
}
