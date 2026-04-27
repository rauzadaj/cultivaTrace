<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Farm;
use App\Entity\Organization;
use App\Entity\User;
use App\Enum\LicenseStatus;
use Symfony\Component\HttpFoundation\Response;

final class FarmApiTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetSchema([
            Organization::class,
            User::class,
            Farm::class,
        ]);
    }

    public function testPostFarmCreatesFarmWhenTenantLicenseIsActive(): void
    {
        $organization = $this->createOrganization('Org Farm Active');
        $organization->setLicenseStatus(LicenseStatus::ACTIVE);
        $admin = $this->createUser($organization, 'farm-active-admin@test.local', role: 'ROLE_ORG_ADMIN');
        $this->entityManager->flush();
        $this->authorizeClient($admin);

        $this->apiJsonRequest('POST', '/api/farms', [
            'name' => 'Farm Active',
        ]);

        $this->assertStatusCode(Response::HTTP_CREATED);
    }

    public function testPostFarmReturnsForbiddenWhenTenantLicenseIsPending(): void
    {
        $organization = $this->createOrganization('Org Farm Pending');
        $organization->setLicenseStatus(LicenseStatus::PENDING);
        $admin = $this->createUser($organization, 'farm-pending-admin@test.local', role: 'ROLE_ORG_ADMIN');
        $this->entityManager->flush();
        $this->authorizeClient($admin);

        $this->apiJsonRequest('POST', '/api/farms', [
            'name' => 'Farm Pending',
        ]);

        $this->assertStatusCode(Response::HTTP_FORBIDDEN);
        self::assertStringContainsString('status "pending"', $this->client->getResponse()->getContent() ?: '');
    }

    public function testPatchFarmReturnsForbiddenWhenTenantLicenseIsPending(): void
    {
        $organization = $this->createOrganization('Org Farm Patch Pending');
        $organization->setLicenseStatus(LicenseStatus::PENDING);
        $admin = $this->createUser($organization, 'farm-patch-admin@test.local', role: 'ROLE_ORG_ADMIN');
        $farm = $this->createFarm($organization, 'Farm Patch Pending');
        $this->entityManager->flush();
        $this->authorizeClient($admin);

        $this->client->request(
            'PATCH',
            sprintf('/api/farms/%s', $farm->getId()),
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/merge-patch+json',
                'HTTP_ACCEPT' => 'application/ld+json',
            ],
            json_encode(['name' => 'Blocked Farm Update'], JSON_THROW_ON_ERROR),
        );

        $this->assertStatusCode(Response::HTTP_FORBIDDEN);
        self::assertStringContainsString('status "pending"', $this->client->getResponse()->getContent() ?: '');
    }

    public function testGetFarmsIsNotBlockedWhenTenantLicenseIsPending(): void
    {
        $organization = $this->createOrganization('Org Farm Read Pending');
        $organization->setLicenseStatus(LicenseStatus::PENDING);
        $user = $this->createUser($organization, 'farm-read@test.local');
        $this->createFarm($organization, 'Farm Read Pending');
        $this->entityManager->flush();
        $this->authorizeClient($user);

        $this->client->request('GET', '/api/farms');

        $this->assertStatusCode(Response::HTTP_OK);
    }
}
