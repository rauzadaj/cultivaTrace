<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Farm;
use App\Entity\InputRecord;
use App\Entity\Organization;
use App\Entity\Plant;
use App\Entity\Room;
use App\Entity\Strain;
use App\Entity\User;
use App\Enum\LicenseStatus;
use Symfony\Component\HttpFoundation\Response;

final class InputRecordApiTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetSchema([
            Organization::class,
            User::class,
            Farm::class,
            Room::class,
            Strain::class,
            Plant::class,
            InputRecord::class,
        ]);
    }

    public function testPostInputRecordCreatesRecordWhenTenantLicenseIsActive(): void
    {
        [$user, $plant] = $this->createInputRecordFixture('Org Input Active', LicenseStatus::ACTIVE);
        $this->authorizeClient($user);

        $this->apiJsonRequest('POST', '/api/input_records', [
            'plant' => sprintf('/api/plants/%s', $plant->getId()),
            'inputType' => 'water',
            'productName' => 'Reverse Osmosis',
            'quantity' => '2.500',
            'unit' => 'L',
            'appliedAt' => '2026-03-18',
        ]);

        $this->assertStatusCode(Response::HTTP_CREATED);
    }

    public function testPostInputRecordReturnsForbiddenWhenTenantLicenseIsPending(): void
    {
        [$user, $plant] = $this->createInputRecordFixture('Org Input Pending', LicenseStatus::PENDING);
        $this->authorizeClient($user);

        $this->apiJsonRequest('POST', '/api/input_records', [
            'plant' => sprintf('/api/plants/%s', $plant->getId()),
            'inputType' => 'water',
            'productName' => 'Reverse Osmosis',
            'quantity' => '2.500',
            'unit' => 'L',
            'appliedAt' => '2026-03-18',
        ]);

        $this->assertStatusCode(Response::HTTP_FORBIDDEN);
        self::assertStringContainsString('status "pending"', $this->client->getResponse()->getContent() ?: '');
    }

    public function testGetInputRecordsIsNotBlockedWhenTenantLicenseIsPending(): void
    {
        [$user, $plant] = $this->createInputRecordFixture('Org Input Read Pending', LicenseStatus::PENDING);

        $record = new InputRecord();
        $record->setTenantId($plant->getTenantId());
        $record->setPlant($plant);
        $record->setInputType('water');
        $record->setProductName('Existing Watering');
        $record->setQuantity('1.000');
        $record->setUnit('L');
        $record->setAppliedAt(new \DateTimeImmutable('2026-03-17'));
        $record->setAppliedBy($user);
        $this->entityManager->persist($record);
        $this->entityManager->flush();

        $this->authorizeClient($user);
        $this->client->request('GET', '/api/input_records');

        $this->assertStatusCode(Response::HTTP_OK);
    }

    /**
     * @return array{0: User, 1: Plant}
     */
    private function createInputRecordFixture(string $organizationName, LicenseStatus $licenseStatus): array
    {
        $organization = $this->createOrganization($organizationName);
        $organization->setLicenseStatus($licenseStatus);
        $user = $this->createUser($organization, sprintf('%s@test.local', strtolower(str_replace(' ', '-', $organizationName))));
        $farm = $this->createFarm($organization, sprintf('%s Farm', $organizationName));
        $room = $this->createRoom($farm, sprintf('%s Room', $organizationName));
        $strain = $this->createStrain($organization, sprintf('%s Strain', $organizationName));
        $plant = $this->createPlant($room, $user, $strain, rfidTag: sprintf('%s-PLANT', strtoupper(str_replace(' ', '-', $organizationName))));
        $this->entityManager->flush();

        return [$user, $plant];
    }
}
