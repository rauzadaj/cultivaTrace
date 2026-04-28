<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Organization;
use App\Entity\Strain;
use App\Entity\User;
use App\Enum\LicenseStatus;
use Symfony\Component\HttpFoundation\Response;

final class StrainApiTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetSchema([
            Organization::class,
            User::class,
            Strain::class,
        ]);
    }

    public function testPostStrainCreatesStrainWhenTenantLicenseIsActive(): void
    {
        $organization = $this->createOrganization('Org Strain Active');
        $organization->setLicenseStatus(LicenseStatus::ACTIVE);
        $admin = $this->createUser($organization, 'strain-active-admin@test.local', role: 'ROLE_ORG_ADMIN');
        $this->entityManager->flush();
        $this->authorizeClient($admin);

        $this->apiJsonRequest('POST', '/api/strains', [
            'name' => 'Strain Active',
            'genetics' => 'hybrid',
            'cannabisType' => 'marijuana',
        ]);

        $this->assertStatusCode(Response::HTTP_CREATED);
    }

    public function testPostStrainReturnsForbiddenWhenTenantLicenseIsPending(): void
    {
        $organization = $this->createOrganization('Org Strain Pending');
        $organization->setLicenseStatus(LicenseStatus::PENDING);
        $admin = $this->createUser($organization, 'strain-pending-admin@test.local', role: 'ROLE_ORG_ADMIN');
        $this->entityManager->flush();
        $this->authorizeClient($admin);

        $this->apiJsonRequest('POST', '/api/strains', [
            'name' => 'Strain Pending',
            'genetics' => 'hybrid',
            'cannabisType' => 'marijuana',
        ]);

        $this->assertStatusCode(Response::HTTP_FORBIDDEN);
        self::assertStringContainsString('status "pending"', $this->client->getResponse()->getContent() ?: '');
    }

    public function testPatchStrainReturnsForbiddenWhenTenantLicenseIsPending(): void
    {
        $organization = $this->createOrganization('Org Strain Patch Pending');
        $organization->setLicenseStatus(LicenseStatus::PENDING);
        $admin = $this->createUser($organization, 'strain-patch-admin@test.local', role: 'ROLE_ORG_ADMIN');
        $strain = $this->createStrain($organization, 'Strain Patch Pending');
        $this->entityManager->flush();
        $this->authorizeClient($admin);

        $this->client->request(
            'PATCH',
            sprintf('/api/strains/%s', $strain->getId()),
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/merge-patch+json',
                'HTTP_ACCEPT' => 'application/ld+json',
            ],
            json_encode(['notes' => 'Blocked strain update'], JSON_THROW_ON_ERROR),
        );

        $this->assertStatusCode(Response::HTTP_FORBIDDEN);
        self::assertStringContainsString('status "pending"', $this->client->getResponse()->getContent() ?: '');
    }

    public function testGetStrainsIsNotBlockedWhenTenantLicenseIsPending(): void
    {
        $organization = $this->createOrganization('Org Strain Read Pending');
        $organization->setLicenseStatus(LicenseStatus::PENDING);
        $user = $this->createUser($organization, 'strain-read@test.local');
        $this->createStrain($organization, 'Strain Read Pending');
        $this->entityManager->flush();
        $this->authorizeClient($user);

        $this->client->request('GET', '/api/strains');

        $this->assertStatusCode(Response::HTTP_OK);
    }
}
