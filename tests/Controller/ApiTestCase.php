<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Farm;
use App\Entity\Organization;
use App\Entity\Plant;
use App\Entity\Room;
use App\Entity\Strain;
use App\Entity\User;
use App\Enum\PlantStage;
use App\Enum\PlantStatus;
use App\Enum\UserAccountStatus;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\BrowserKit\CookieJar;
use Symfony\Component\BrowserKit\History;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class ApiTestCase extends KernelTestCase
{
    protected KernelBrowser $client;
    protected ContainerInterface $container;
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();
        $this->container = static::$kernel->getContainer();
        $this->client = new KernelBrowser(static::$kernel, [], new History(), new CookieJar());
        $this->client->setServerParameter('HTTP_ACCEPT', 'application/ld+json');
        /** @var ManagerRegistry $doctrine */
        $doctrine = $this->container->get('doctrine');
        $this->entityManager = $doctrine->getManager();
        $filters = $this->entityManager->getFilters();
        if ($filters->isEnabled('tenant_filter')) {
            $filters->disable('tenant_filter');
        }
    }

    protected function tearDown(): void
    {
        $filters = $this->entityManager->getFilters();
        if ($filters->isEnabled('tenant_filter')) {
            $filters->disable('tenant_filter');
        }

        $this->entityManager->close();
        unset($this->container, $this->entityManager, $this->client);

        parent::tearDown();
    }

    /**
     * @param list<class-string> $classes
     */
    protected function resetSchema(array $classes): void
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

    protected function createOrganization(string $name, string $country = 'FR'): Organization
    {
        $organization = new Organization();
        $organization->setName($name);
        $organization->setCountry($country);

        $this->entityManager->persist($organization);

        return $organization;
    }

    protected function createUser(
        Organization $organization,
        string $email,
        string $plainPassword = 'test123',
        string $role = 'ROLE_ORG_USER',
        ?array $roles = null,
    ): User {
        $user = new User();
        $user->setEmail($email);
        $user->setOrganization($organization);
        $user->setRoles($roles ?? [$role]);
        $user->setPassword('test-password');
        $user->setAccountStatus(UserAccountStatus::ACTIVE);
        $user->setEmailVerifiedAt(new \DateTimeImmutable());
        $user->setEmailVerificationTokenHash(null);
        $user->setEmailVerificationExpiresAt(null);

        $this->entityManager->persist($user);

        return $user;
    }

    protected function createFarm(Organization $organization, string $name): Farm
    {
        $farm = new Farm();
        $farm->setOrganization($organization);
        $farm->setTenantId($organization->getId());
        $farm->setName($name);

        $this->entityManager->persist($farm);

        return $farm;
    }

    protected function createRoom(Farm $farm, string $name, string $type = 'veg'): Room
    {
        $room = new Room();
        $room->setFarm($farm);
        $room->setTenantId($farm->getTenantId());
        $room->setName($name);
        $room->setType($type);
        $room->setCapacityMax(100);

        $this->entityManager->persist($room);

        return $room;
    }

    protected function createStrain(Organization $organization, string $name, string $cannabisType = 'marijuana'): Strain
    {
        $strain = new Strain();
        $strain->setTenantId($organization->getId());
        $strain->setName($name);
        $strain->setGenetics($name);
        $strain->setCannabisType($cannabisType);
        $strain->setThcPercentage(18.5);
        $strain->setFloweringDays(63);

        $this->entityManager->persist($strain);

        return $strain;
    }

    protected function createPlant(
        Room $room,
        User $createdBy,
        ?Strain $strain = null,
        PlantStage $stage = PlantStage::GERMINATION,
        PlantStatus $status = PlantStatus::ACTIVE,
        string $rfidTag = 'RFID-001',
        string $germinatedAt = '-1 day',
    ): Plant {
        $plant = new Plant();
        $plant->setRoom($room);
        $plant->setTenantId($room->getTenantId());
        $plant->setCreatedBy($createdBy);
        $plant->setStrain($strain);
        $plant->setStage($stage);
        $plant->setStatus($status);
        $plant->setRfidTag($rfidTag);
        $plant->setGerminatedAt(new \DateTimeImmutable($germinatedAt));

        $this->entityManager->persist($plant);

        return $plant;
    }

    protected function authorizeClient(User $user, string $plainPassword = 'test123'): string
    {
        $token = $this->container->get('lexik_jwt_authentication.jwt_manager')->create($user);
        $this->client->setServerParameter('HTTP_AUTHORIZATION', sprintf('Bearer %s', $token));

        return $token;
    }

    protected function assertStatusCode(int $expectedStatusCode): void
    {
        $content = $this->client->getResponse()->getContent();

        self::assertSame(
            $expectedStatusCode,
            $this->client->getResponse()->getStatusCode(),
            $content === false ? '' : $content,
        );
    }

    protected function apiJsonRequest(string $method, string $uri, array $payload = []): void
    {
        $this->client->request(
            $method,
            $uri,
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/ld+json',
                'HTTP_ACCEPT' => 'application/ld+json',
            ],
            json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }
}
