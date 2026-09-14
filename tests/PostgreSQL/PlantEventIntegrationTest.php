<?php

declare(strict_types=1);

namespace App\Tests\PostgreSQL;

use App\Entity\{Farm, Organization, Plant, PlantEvent, Room, User};
use App\Enum\RoomType;
use App\Repository\PlantEventRepository;
use App\Service\HashChainService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\InputStream;

final class PlantEventIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get('doctrine')->getManager();
        self::assertSame(getenv('PLANT_EVENT_TEST_DATABASE'), $this->em->getConnection()->fetchOne('SELECT current_database()'));
        self::assertSame('16', explode('.', $this->em->getConnection()->fetchOne('SHOW server_version'))[0]);
    }

    protected function tearDown(): void
    {
        $connection = $this->em->getConnection();
        while ($connection->isTransactionActive()) {
            $connection->rollBack();
        }
        $this->em->close();
        parent::tearDown();
    }

    public function testActualMigrationsAndInsert(): void
    {
        $db = $this->em->getConnection();
        self::assertGreaterThanOrEqual(32, (int) $db->fetchOne('SELECT count(*) FROM doctrine_migration_versions'));
        self::assertSame('jsonb', $db->fetchOne("SELECT udt_name FROM information_schema.columns WHERE table_name = 'plant_event' AND column_name = 'payload'"));
        self::assertSame(1, (int) $db->fetchOne("SELECT count(*) FROM pg_trigger WHERE tgname = 'trg_plant_event_append_only' AND tgenabled = 'O'"));
        [$plant, $user] = $this->fixture();
        $event = $this->repository()->appendEvent($plant, 'note', $user, ['message' => 'insert']);
        self::assertSame(1, (int) $db->fetchOne('SELECT count(*) FROM plant_event WHERE id = ?', [(string) $event->getId()]));
    }

    public static function mutations(): iterable
    {
        yield 'update' => [false];
        yield 'delete' => [true];
    }

    #[DataProvider('mutations')]
    public function testSqlRejectsMutationIndependentlyOfOrm(bool $delete): void
    {
        [$plant, $user] = $this->fixture();
        $event = $this->repository()->appendEvent($plant, 'note', $user, null, 'original');
        $db = $this->em->getConnection();
        try {
            $db->executeStatement($delete ? 'DELETE FROM plant_event WHERE id = ?' : "UPDATE plant_event SET notes = 'changed' WHERE id = ?", [(string) $event->getId()]);
            self::fail('The real migration trigger must reject this direct SQL statement.');
        } catch (\Doctrine\DBAL\Exception\DriverException $e) {
            self::assertSame('P0001', $e->getSQLState());
            self::assertStringContainsString('append-only', $e->getMessage());
        }
        self::assertSame('original', $db->fetchOne('SELECT notes FROM plant_event WHERE id = ?', [(string) $event->getId()]));
    }

    #[DataProvider('mutations')]
    public function testOrmRejectsMutationBeforeSql(bool $delete): void
    {
        [$plant, $user] = $this->fixture();
        $event = $this->repository()->appendEvent($plant, 'note', $user, null, 'original');
        try {
            if ($delete) {
                $this->em->remove($event);
            } else {
                $event->setNotes('changed');
            }
            $this->em->flush();
            self::fail('The ORM listener must reject the mutation.');
        } catch (\LogicException $e) {
            // A database trigger failure is a DBAL exception, not this ORM LogicException.
            self::assertStringContainsString('append-only', $e->getMessage());
        }
        self::assertSame('original', $this->em->getConnection()->fetchOne('SELECT notes FROM plant_event WHERE id = ?', [(string) $event->getId()]));
    }

    public static function lengths(): iterable
    {
        yield 'single event' => [1];
        yield 'three events' => [3];
    }

    #[DataProvider('lengths')]
    public function testSingleKeyPayloadSurvivesReload(int $length): void
    {
        [$plant, $user] = $this->fixture();
        for ($i = 0; $i < $length; ++$i) {
            $this->repository()->appendEvent($plant, 'note', $user, ['message' => 'event '.$i]);
        }
        $this->em->clear();
        self::assertSame(['valid' => true, 'broken_at' => null, 'checked' => $length], $this->chain()->verify($plant->getId()));
    }

    #[DataProvider('lengths')]
    public function testMultiKeyJsonbPayloadMustRemainVerifiableAfterReload(int $length): void
    {
        [$plant, $user] = $this->fixture();
        $payload = ['quantity' => 10, 'unit' => 'g', 'metadata' => ['source' => 'manual', 'operator' => 'test']];
        $event = $this->repository()->appendEvent($plant, 'note', $user, $payload);
        $id = $event->getId();
        $storedHash = $event->getHashSelf();
        for ($i = 1; $i < $length; ++$i) {
            $this->repository()->appendEvent($plant, 'note', $user, ['message' => 'next '.$i]);
        }
        $this->em->clear();
        $reloaded = $this->em->find(PlantEvent::class, $id);
        self::assertInstanceOf(PlantEvent::class, $reloaded);
        $result = $this->chain()->verify($plant->getId());
        $this->evidence('jsonb-'.$length, [
            'event_id' => (string) $id, 'length' => $length,
            'occurred_at_unix' => $reloaded->getOccurredAt()->format('U'),
            'hash_previous' => $reloaded->getHashPrevious(),
            'payload_original' => $payload, 'payload_reloaded' => $reloaded->getPayload(),
            'hash_expected_stored' => $storedHash,
            'hash_obtained_recomputed' => $this->chain()->computeHash($reloaded, str_repeat('0', 64)),
            'verification' => $result,
        ]);
        // Intentionally a release gate: do not skip or normalize historical payloads to make it green.
        self::assertTrue($result['valid'], 'P0-06 BLOCKED: JSONB reload changes the historical hash input. See var/plant-event-evidence/jsonb-'.$length.'.json');
    }

    public static function predecessorCases(): iterable
    {
        yield 'existing H0' => [true];
        yield 'first event' => [false];
    }

    #[DataProvider('predecessorCases')]
    public function testIndependentConcurrentAppendsProduceOneChain(bool $withPredecessor): void
    {
        [$plant, $user] = $this->fixture();
        $h0 = $withPredecessor ? $this->repository()->appendEvent($plant, 'note', $user, ['message' => 'H0'])->getHashSelf() : str_repeat('0', 64);
        $db = $this->em->getConnection();
        $pidA = (int) $db->fetchOne('SELECT pg_backend_pid()');
        $db->beginTransaction();
        self::assertSame($h0, $this->repository()->findLastForPlant($plant->getId())?->getHashSelf() ?? str_repeat('0', 64));
        // Both transactions read H0 before either writes. STDIN is a barrier, not a DB lock.
        $input = new InputStream();
        $worker = new Process([PHP_BINARY, 'tests/PostgreSQL/append-worker.php', (string) $plant->getId(), (string) $user->getId()], dirname(__DIR__, 2), timeout: 20);
        $worker->setInput($input);
        $worker->start();
        $ready = null;
        $blocked = false;
        try {
            $deadline = microtime(true) + 10;
            do {
                $lines = explode("\n", trim($worker->getOutput()));
                $ready = json_decode($lines[0], true);
                if (is_array($ready) && isset($ready['pid'])) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            self::assertIsArray($ready, $worker->getErrorOutput());
            self::assertNotSame($pidA, $ready['pid']);
            self::assertSame($h0, $ready['read_previous']);
            $a = $this->repository()->appendEvent($plant, 'note', $user, ['message' => 'A']);
            $input->write("append\n");
            $input->close();
            $deadline = microtime(true) + 10;
            do {
                $worker->getOutput(); // Also pumps the STDIN barrier on Windows.
                $blocked = $db->fetchOne("SELECT wait_event_type FROM pg_stat_activity WHERE pid = ?", [$ready['pid']]) === 'Lock';
                if ($blocked || !$worker->isRunning()) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            $db->commit();
            self::assertSame(0, $worker->wait(), $worker->getErrorOutput());
            $lines = explode("\n", trim($worker->getOutput()));
            $b = json_decode(end($lines), true, flags: JSON_THROW_ON_ERROR);
            $this->evidence('concurrency-'.($withPredecessor ? 'existing' : 'first'), [
                'pid_a' => $pidA, 'pid_b' => $ready['pid'], 'both_read_h0' => $h0,
                'a_previous' => $a->getHashPrevious(), 'a_self' => $a->getHashSelf(),
                'b_previous' => $b['previous'], 'b_self' => $b['self'], 'b_waited_on_lock' => $blocked,
            ]);
            self::assertSame($a->getHashSelf(), $b['previous'], 'Concurrent transactions branched from the same H0.');
            self::assertTrue($blocked, 'Writer B must wait until writer A commits.');
            $this->em->clear();
            self::assertTrue($this->chain()->verify($plant->getId())['valid']);
        } finally {
            $input->close();
            if ($db->isTransactionActive()) {
                $db->rollBack();
            }
            $worker->stop(0);
        }
    }

    public function testOuterRollbackUndoesBusinessMutationAndAppend(): void
    {
        [$plant, $user] = $this->fixture();
        $id = (string) $plant->getId();
        $db = $this->em->getConnection();
        $db->beginTransaction();
        $plant->setRfidTag('uncommitted');
        $this->repository()->appendEvent($plant, 'note', $user, ['message' => 'rolled back']);
        self::assertTrue($db->isTransactionActive(), 'appendEvent must not commit the caller transaction.');
        $db->rollBack();
        $this->em->clear();
        self::assertNull($db->fetchOne('SELECT rfid_tag FROM plant WHERE id = ?', [$id]));
        self::assertSame(0, (int) $db->fetchOne('SELECT count(*) FROM plant_event WHERE plant_id = ?', [$id]));
    }

    public static function failures(): iterable
    {
        yield 'JSON encoding before flush' => [false];
        yield 'SQL constraint during flush' => [true];
    }

    #[DataProvider('failures')]
    public function testAppendFailureRollsBackPendingBusinessState(bool $sqlFailure): void
    {
        [$plant, $user] = $this->fixture();
        $db = $this->em->getConnection();
        $id = (string) $plant->getId();
        $plant->setRfidTag('must not commit');
        try {
            $this->repository()->appendEvent($plant, $sqlFailure ? str_repeat('x', 101) : 'note', $user, $sqlFailure ? null : ['invalid' => NAN]);
            self::fail('Append was expected to fail.');
        } catch (\JsonException|\Doctrine\DBAL\Exception\DriverException $e) {
            self::assertFalse($this->em->isOpen(), 'Failed pending changes must not be flushable later.');
        }
        self::assertFalse($db->isTransactionActive());
        self::assertNull($db->fetchOne('SELECT rfid_tag FROM plant WHERE id = ?', [$id]));
        self::assertSame(0, (int) $db->fetchOne('SELECT count(*) FROM plant_event WHERE plant_id = ?', [$id]));
    }

    public function testRepeatableReadCannotSilentlyAppendFromStaleSnapshot(): void
    {
        [$plant, $user] = $this->fixture();
        $db = $this->em->getConnection();
        $db->beginTransaction();
        $db->executeStatement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $db->fetchOne('SELECT count(*) FROM plant_event');
        try {
            $this->repository()->appendEvent($plant, 'note', $user);
            self::fail('A stale transaction snapshot must not be allowed to fork the chain.');
        } catch (\LogicException $e) {
            self::assertStringContainsString('READ COMMITTED', $e->getMessage());
        }
        $db->rollBack();
        self::assertSame(0, (int) $db->fetchOne('SELECT count(*) FROM plant_event WHERE plant_id = ?', [(string) $plant->getId()]));
    }

    private function repository(): PlantEventRepository
    {
        /** @var PlantEventRepository $repository */
        $repository = $this->em->getRepository(PlantEvent::class);
        return $repository;
    }

    private function chain(): HashChainService
    {
        return self::getContainer()->get(HashChainService::class);
    }

    /** @return array{Plant, User} */
    private function fixture(): array
    {
        $org = (new Organization())->setName('PostgreSQL integration')->setCountry('CA');
        $user = (new User())->setEmail(bin2hex(random_bytes(8)).'@test.local')->setOrganization($org)->setPassword('test')->setRoles(['ROLE_ORG_USER']);
        $farm = (new Farm())->setName('Farm')->setOrganization($org)->setTenantId($org->getId());
        $room = (new Room())->setName('Room')->setFarm($farm)->setTenantId($org->getId())->setType(RoomType::Veg)->setCapacityMax(100);
        $plant = (new Plant())->setRoom($room)->setTenantId($org->getId())->setCreatedBy($user)->setGerminatedAt(new \DateTimeImmutable('-1 day'));
        foreach ([$org, $user, $farm, $room, $plant] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();
        return [$plant, $user];
    }

    private function evidence(string $name, array $data): void
    {
        $dir = dirname(__DIR__, 2).'/var/plant-event-evidence';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($dir.'/'.$name.'.json', json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)."\n");
    }
}
