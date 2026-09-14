<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Alert;
use App\Entity\Farm;
use App\Entity\HarvestRecord;
use App\Entity\Organization;
use App\Entity\Plant;
use App\Entity\PlantEvent;
use App\Entity\Room;
use App\Entity\Sensor;
use App\Entity\Strain;
use App\Entity\User;
use App\Enum\LicenseStatus;
use PHPUnit\Framework\Attributes\DataProvider;

final class LicenseMutationProtectionTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetSchema([
            Organization::class, User::class, Farm::class, Room::class, Strain::class,
            Plant::class, PlantEvent::class, HarvestRecord::class, Sensor::class, Alert::class,
        ]);
    }

    /** @return iterable<string, array{LicenseStatus, string}> */
    public static function mutations(): iterable
    {
        foreach (LicenseStatus::cases() as $status) {
            foreach (['plant', 'room', 'sensor', 'delete_sensor'] as $operation) {
                yield $status->value . '-' . $operation => [$status, $operation];
            }
        }
    }

    #[DataProvider('mutations')]
    public function testOnlyActiveLicenseCanMutate(LicenseStatus $status, string $operation): void
    {
        $organization = $this->createOrganization('License checks');
        $organization->setLicenseStatus($status);
        $user = $this->createUser($organization, 'license@test.local', role: 'ROLE_ORG_ADMIN');
        $farm = $this->createFarm($organization, 'Farm');
        $room = $this->createRoom($farm, 'Original room');
        $plant = $this->createPlant($room, $user, rfidTag: 'ORIGINAL');
        $sensor = (new Sensor())->setTenantId($organization->getId())->setRoom($room)
            ->setType('temperature')->setDeviceId('ORIGINAL');
        $this->entityManager->persist($sensor);
        $this->entityManager->flush();

        [$entity, $url, $payload] = match ($operation) {
            'plant' => [$plant, '/api/plants/' . $plant->getId(), ['rfidTag' => 'CHANGED']],
            'room' => [$room, '/api/rooms/' . $room->getId(), ['name' => 'CHANGED']],
            default => [$sensor, '/api/sensors/' . $sensor->getId(), ['deviceId' => 'CHANGED']],
        };
        $entityClass = $entity::class;
        $entityId = $entity->getId();
        $this->authorizeClient($user);

        if ($operation === 'delete_sensor') {
            $this->client->request('DELETE', $url);
        } else {
            $this->client->request('PATCH', $url, [], [], [
                'CONTENT_TYPE' => 'application/merge-patch+json',
                'HTTP_ACCEPT' => 'application/ld+json',
            ], json_encode($payload, JSON_THROW_ON_ERROR));
        }

        $active = $status === LicenseStatus::ACTIVE;
        $deniedStatus = in_array($status, [LicenseStatus::SUSPENDED, LicenseStatus::EXPIRED], true) ? 401 : 403;
        $this->assertStatusCode($active ? ($operation === 'delete_sensor' ? 204 : 200) : $deniedStatus);

        // Reload persisted state: a denied request must not have flushed the changed entity.
        $this->entityManager->clear();
        $stored = $this->entityManager->find($entityClass, $entityId);
        if ($active && $operation === 'delete_sensor') {
            self::assertNull($stored);
        } else {
            self::assertNotNull($stored);
            $actual = match (true) {
                $stored instanceof Plant => $stored->getRfidTag(),
                $stored instanceof Room => $stored->getName(),
                $stored instanceof Sensor => $stored->getDeviceId(),
                default => null,
            };
            self::assertSame($active ? 'CHANGED' : ($operation === 'room' ? 'Original room' : 'ORIGINAL'), $actual);
        }
    }
}
