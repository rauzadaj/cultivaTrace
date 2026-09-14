<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Persistence\Doctrine;

use App\Entity\Farm;
use App\Entity\HarvestRecord;
use App\Entity\Organization;
use App\Entity\Plant;
use App\Entity\PlantEvent;
use App\Entity\Room;
use App\Entity\Strain;
use App\Entity\User;
use App\Repository\PlantEventRepository;
use App\Service\HashChainService;
use App\Tests\Controller\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class PlantEventAppendOnlyTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetSchema([
            Organization::class, User::class, Farm::class, Room::class, Strain::class,
            Plant::class, PlantEvent::class, HarvestRecord::class,
        ]);
    }

    /** @return iterable<string, array{bool}> */
    public static function forbiddenMutations(): iterable
    {
        yield 'update' => [false];
        yield 'delete' => [true];
    }

    #[DataProvider('forbiddenMutations')]
    public function testOrmRejectsHistoricalMutationWithoutDatabaseTrigger(bool $delete): void
    {
        [$plant, $user] = $this->fixture();
        /** @var PlantEventRepository $repository */
        $repository = $this->entityManager->getRepository(PlantEvent::class);
        $event = $repository->appendEvent($plant, 'note', $user, ['message' => 'original'], 'original');
        $id = $event->getId();
        $hash = $event->getHashSelf();
        $this->entityManager->clear();
        $event = $this->entityManager->find(PlantEvent::class, $id);
        self::assertInstanceOf(PlantEvent::class, $event);

        try {
            if ($delete) {
                $this->entityManager->remove($event);
            } else {
                $event->setNotes('silently changed');
            }
            $this->entityManager->flush();
            self::fail('Historical event mutation must be rejected by the registered ORM listener.');
        } catch (\LogicException $exception) {
            self::assertStringContainsString('append-only', $exception->getMessage());
        }

        $row = $this->entityManager->getConnection()->fetchAssociative('SELECT notes, hash_self FROM plant_event');
        self::assertIsArray($row);
        self::assertSame('original', $row['notes']);
        self::assertSame($hash, $row['hash_self']);
    }

    public function testCorrectionCanAppendAndPreservesOriginal(): void
    {
        [$plant, $user] = $this->fixture();
        /** @var PlantEventRepository $repository */
        $repository = $this->entityManager->getRepository(PlantEvent::class);
        $first = $repository->appendEvent($plant, 'note', $user, ['message' => 'original']);
        $firstHash = $first->getHashSelf();
        // The payload is illustrative: this test does not introduce a new regulatory correction schema.
        $next = $repository->appendEvent($plant, 'note', $user, ['correctsEventId' => (string) $first->getId()]);
        self::assertSame($firstHash, $next->getHashPrevious());
        self::assertSame($firstHash, $first->getHashSelf());
        $plantId = $plant->getId();
        $this->entityManager->clear();

        $chain = self::getContainer()->get(HashChainService::class);
        self::assertSame(['valid' => true, 'broken_at' => null, 'checked' => 2], $chain->verify($plantId));
    }

    /** @return array{Plant, User} */
    private function fixture(): array
    {
        $org = $this->createOrganization('Append-only');
        $user = $this->createUser($org, 'append@test.local');
        $room = $this->createRoom($this->createFarm($org, 'Farm'), 'Room');
        $plant = $this->createPlant($room, $user);
        $this->entityManager->flush();

        return [$plant, $user];
    }
}
