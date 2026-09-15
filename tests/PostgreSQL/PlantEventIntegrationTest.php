<?php

declare(strict_types=1);

namespace App\Tests\PostgreSQL;

use App\Entity\{Farm, Organization, Plant, PlantEvent, Room, User};
use App\Enum\RoomType;
use App\Repository\PlantEventRepository;
use App\Service\HashChainService;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
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
            'algorithm' => 'HMAC-SHA256 (main 869e592)',
            'notes' => $reloaded->getNotes(), 'photo_urls' => $reloaded->getPhotoUrls(),
            'ip_address' => $reloaded->getIpAddress(),
            'occurred_at_unix' => $reloaded->getOccurredAt()->format('U'),
            'hash_previous' => $reloaded->getHashPrevious(),
            'payload_original' => $payload, 'payload_reloaded' => $reloaded->getPayload(),
            'hash_expected_stored' => $storedHash,
            'hash_obtained_recomputed' => $this->chain()->computeHash($reloaded, str_repeat('0', 64)),
            'verification' => $result,
        ]);
        // Keep the original release gate: only new writes may prepare their JSON before hashing.
        self::assertTrue($result['valid'], 'P0-06: a new JSONB event must verify after reload. See var/plant-event-evidence/jsonb-'.$length.'.json');
    }

    public static function jsonbValues(): iterable
    {
        yield 'SQL null and null photos' => [null, null];
        yield 'empty arrays' => [[], []];
        $photos = ['https://example.test/é.jpg', 'https://example.test/a.jpg', 'https://example.test/é.jpg'];
        yield 'nested objects and ordered lists' => [
            ['long_key' => ['zz' => 1, 'a' => 2], 'b' => [['beta' => 3, 'a' => 4], ['value' => 'second'], ['beta' => 3, 'a' => 4]]],
            $photos,
        ];
        yield 'Unicode and escaping' => [['échantillon' => '🌱/é', 'a' => "line\nbreak", 'quote' => '"\\|'], $photos];
        yield 'negative zero' => [['reading' => -0.0], $photos];
        yield 'floating point limits and exponents' => [
            ['x' => 1.0, 'y' => 1.0e16, 'z' => 1.0e20, 'tiny' => 1.0e-7, 'min' => PHP_FLOAT_MIN, 'max' => PHP_FLOAT_MAX],
            $photos,
        ];
        yield 'integer limits' => [['max' => PHP_INT_MAX, 'min' => PHP_INT_MIN], $photos];
        yield 'sparse numeric object keys' => [['value' => [10 => 'ten', 2 => 'two']], $photos];
        yield 'distinct scalar values' => [['yes' => true, 'no' => false, 'nil' => null, 'empty' => '', 'zero' => 0, 'string_zero' => '0'], $photos];
    }

    /**
     * @param array<string, mixed>|null $payload
     * @param list<string>|null $photos
     */
    #[DataProvider('jsonbValues')]
    public function testNewJsonbValuesPreserveMeaningAndVerifyAfterReload(?array $payload, ?array $photos): void
    {
        [$plant, $user] = $this->fixture();
        $db = $this->em->getConnection();
        $originalJson = Type::getType(Types::JSON)->convertToDatabaseValue($payload, $db->getDatabasePlatform());
        $event = $this->repository()->appendEvent($plant, 'note', $user, $payload, "échantillon|note\n", $photos);
        $id = $event->getId();
        $preparedPayload = $event->getPayload();
        $storedHash = $event->getHashSelf();
        self::assertSame(1, (int) $db->fetchOne(
            'SELECT CASE WHEN payload IS NOT DISTINCT FROM CAST(:original AS jsonb) THEN 1 ELSE 0 END FROM plant_event WHERE id = :id',
            ['original' => $originalJson, 'id' => (string) $id],
        ), 'Preparing the hash input must preserve the original JSONB value.');
        $this->em->clear();
        $reloaded = $this->em->find(PlantEvent::class, $id);
        self::assertInstanceOf(PlantEvent::class, $reloaded);
        self::assertSame($preparedPayload, $reloaded->getPayload(), 'The prepared PHP value must survive Doctrine hydration.');
        self::assertSame($photos, $reloaded->getPhotoUrls(), 'Photo order and SQL null versus an empty list must survive.');
        self::assertSame("échantillon|note\n", $reloaded->getNotes());
        self::assertSame($storedHash, $this->chain()->computeHash($reloaded, $reloaded->getHashPrevious()));
        self::assertSame(['valid' => true, 'broken_at' => null, 'checked' => 1], $this->chain()->verify($plant->getId()));
    }

    public static function lossyJsonbValues(): iterable
    {
        yield 'nested empty object must not become a list' => [['value' => new \stdClass()]];
        yield 'numeric object keys must not become a list' => [['value' => (object) ['1' => 'b', '0' => 'a']]];
    }

    /** @param array<string, mixed> $payload */
    #[DataProvider('lossyJsonbValues')]
    public function testLossyJsonbConversionRejectsAppendAndRollsBackPendingBusinessState(array $payload): void
    {
        [$plant, $user] = $this->fixture();
        $db = $this->em->getConnection();
        $id = (string) $plant->getId();
        $plant->setRfidTag('must not commit');
        try {
            $this->repository()->appendEvent($plant, 'note', $user, $payload);
            self::fail('An append must reject a JSON object that Doctrine would change into a JSON list.');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('JSON structure or values', $e->getMessage());
            self::assertFalse($this->em->isOpen(), 'Rejected pending changes must not be flushable later.');
        }
        self::assertFalse($db->isTransactionActive());
        self::assertNull($db->fetchOne('SELECT rfid_tag FROM plant WHERE id = ?', [$id]));
        self::assertSame(0, (int) $db->fetchOne('SELECT count(*) FROM plant_event WHERE plant_id = ?', [$id]));
    }

    public static function historicalPayloads(): iterable
    {
        yield 'valid legacy event remains valid' => [['message' => 'legacy'], true];
        yield 'invalid legacy event remains invalid' => [
            ['quantity' => 10, 'unit' => 'g', 'metadata' => ['source' => 'manual', 'operator' => 'test']],
            false,
        ];
    }

    /** @param array<string, mixed> $payload */
    #[DataProvider('historicalPayloads')]
    public function testNewAppendDoesNotRewriteOrRepairHistoricalEvents(array $payload, bool $valid): void
    {
        [$plant, $user] = $this->fixture();
        // Build a legacy fixture with its original pre-JSONB hash input. This bypasses
        // appendEvent only to reproduce an already-stored historical row, never to update it.
        $legacy = (new PlantEvent())
            ->setPlant($plant)->setUser($user)->setTenantId($plant->getTenantId())
            ->setEventType('note')->setPayload($payload)->setNotes('legacy|échantillon')
            ->setPhotoUrls(['https://example.test/legacy.jpg'])
            ->setOccurredAt(new \DateTimeImmutable('@1770000000'))->setIpAddress('synthetic-legacy-ip')
            ->setHashPrevious(str_repeat('0', 64));
        $legacyHash = $this->chain()->computeHash($legacy, $legacy->getHashPrevious());
        $legacy->setHashSelf($legacyHash);
        $this->em->persist($legacy);
        $this->em->flush();
        $legacyId = (string) $legacy->getId();
        $plantId = $plant->getId();
        $userId = $user->getId();
        $db = $this->em->getConnection();
        $before = $db->fetchAssociative('SELECT * FROM plant_event WHERE id = ?', [$legacyId]);
        $this->em->clear();
        self::assertSame(
            ['valid' => $valid, 'broken_at' => $valid ? null : $legacyId, 'checked' => $valid ? 1 : 0],
            $this->chain()->verify($plantId),
            'The unchanged verifier must characterize the legacy row before any new append.',
        );
        $plant = $this->em->find(Plant::class, $plantId);
        $user = $this->em->find(User::class, $userId);
        self::assertInstanceOf(Plant::class, $plant);
        self::assertInstanceOf(User::class, $user);
        $new = $this->repository()->appendEvent($plant, 'note', $user, ['quantity' => 1, 'unit' => 'g', 'metadata' => ['source' => 'new', 'operator' => 'test']]);
        $newId = $new->getId();
        self::assertSame($legacyHash, $new->getHashPrevious());
        $this->em->clear();
        $new = $this->em->find(PlantEvent::class, $newId);
        self::assertInstanceOf(PlantEvent::class, $new);
        self::assertSame($new->getHashSelf(), $this->chain()->computeHash($new, $legacyHash));
        self::assertSame($before, $db->fetchAssociative('SELECT * FROM plant_event WHERE id = ?', [$legacyId]), 'Every stored historical column must remain byte-for-byte unchanged.');
        self::assertSame(
            ['valid' => $valid, 'broken_at' => $valid ? null : $legacyId, 'checked' => $valid ? 2 : 0],
            $this->chain()->verify($plantId),
            'A valid new event must not make the verifier forgive an invalid historical hash.',
        );
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
            $a = $this->repository()->appendEvent($plant, 'note', $user, ['message' => 'A', 'unit' => 'g', 'metadata' => ['source' => 'concurrent', 'operator' => 'A']]);
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
        $this->repository()->appendEvent($plant, 'note', $user, ['message' => 'rolled back', 'unit' => 'g', 'metadata' => ['source' => 'rollback', 'operator' => 'test']]);
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
        } catch (\InvalidArgumentException|\Doctrine\DBAL\Exception\DriverException $e) {
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
