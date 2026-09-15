<?php

declare(strict_types=1);

namespace App\Repository;

use ApiPlatform\Metadata\Exception\InvalidArgumentException;
use App\Entity\Plant;
use App\Entity\PlantEvent;
use App\Service\HashChainService;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Types\Exception\SerializationFailed;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;

/**
 * PlantEventRepository — APPEND-ONLY.
 *
 * ABSOLUTE RULE: use appendEvent() to create a PlantEvent.
 * Never call EntityManager::remove() or ::flush() on an existing PlantEvent.
 *
 * @extends ServiceEntityRepository<PlantEvent>
 */
class PlantEventRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly HashChainService $hashChainService,
        private readonly RequestStack $requestStack,
        #[Autowire('%env(AUDIT_CHAIN_SECRET)%')]
        private readonly string $auditSecret,
    ) {
        parent::__construct($registry, PlantEvent::class);
    }

    /**
     * Creates and persists a PlantEvent with automatic hash-chaining.
     * This is the ONLY way to create a PlantEvent.
     *
     * @param array<string, mixed>|null $payload
     * @param list<string>|null         $photoUrls
     *
     * Flushes the unit of work for legacy compatibility. Inside a caller-owned
     * transaction, the savepoint is released but only the caller can commit.
     * On failure Doctrine closes/clears the EntityManager; the caller must roll
     * back its outer transaction and obtain a new manager before retrying.
     */
    public function appendEvent(
        Plant   $plant,
        string  $eventType,
        mixed   $user,
        ?array  $payload   = null,
        ?string $notes     = null,
        ?array  $photoUrls = null,
    ): PlantEvent {
        $em = $this->getEntityManager();

        return $em->wrapInTransaction(function () use ($plant, $eventType, $user, $payload, $notes, $photoUrls): PlantEvent {
            $connection = $this->getEntityManager()->getConnection();
            if ($connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
                // Each statement must see commits made while waiting for the plant lock.
                // Read the actual isolation (DBAL's cached default misses raw SET commands).
                if ($connection->fetchOne('SHOW transaction_isolation') !== 'read committed') {
                    throw new \LogicException('PlantEvent append requires PostgreSQL READ COMMITTED isolation.');
                }

                // Lock the parent, which exists even before its first event. NO KEY UPDATE
                // serializes writers without conflicting with FK KEY SHARE locks.
                $lockedId = $connection->fetchOne(
                    'SELECT id FROM plant WHERE id = :id AND tenant_id = :tenant FOR NO KEY UPDATE',
                    ['id' => (string) $plant->getId(), 'tenant' => (string) $plant->getTenantId()],
                );
                if ($lockedId === false) {
                    throw new \LogicException('PlantEvent requires a persisted plant in the same tenant.');
                }

                // Sign the representation Doctrine will actually reload from JSONB.
                // Only new events pass here; historical hashes and verification stay unchanged.
                $payload = $this->prepareJsonForStorage($payload, 'payload');
                $photoUrls = $this->prepareJsonForStorage($photoUrls, 'photoUrls');
            }

            return $this->createEvent($plant, $eventType, $user, $payload, $notes, $photoUrls);
        });
    }

    /**
     * Preserve the JSON document's meaning and require a stable storage round-trip
     * before signing. In particular, associative decoding must not turn {} into [].
     *
     * @template T of array
     * @param T|null $value
     * @return T|null
     */
    private function prepareJsonForStorage(?array $value, string $field): ?array
    {
        if ($value === null) {
            return null;
        }

        $connection = $this->getEntityManager()->getConnection();
        $platform = $connection->getDatabasePlatform();
        $type = Type::getType(Types::JSON);
        try {
            $originalJson = $type->convertToDatabaseValue($value, $platform);
        } catch (SerializationFailed $e) {
            throw new InvalidArgumentException('PlantEvent '.$field.' must contain JSON-serializable values.', 0, $e);
        }

        $storedJson = $connection->fetchOne(
            'SELECT CAST(CAST(:value AS jsonb) AS text)',
            ['value' => $originalJson],
        );
        $prepared = $type->convertToPHPValue($storedJson, $platform);
        if (!is_array($prepared)) {
            throw new InvalidArgumentException('PlantEvent '.$field.' must remain an array after storage.');
        }
        $preparedJson = $type->convertToDatabaseValue($prepared, $platform);
        $check = $connection->fetchAssociative(
            'SELECT CASE WHEN CAST(:original AS jsonb) IS NOT DISTINCT FROM CAST(:prepared AS jsonb) THEN 1 ELSE 0 END AS same_document,
                    CAST(CAST(:prepared AS jsonb) AS text) AS reloaded',
            ['original' => $originalJson, 'prepared' => $preparedJson],
        );
        if ($check === false || (int) $check['same_document'] !== 1) {
            throw new InvalidArgumentException('PlantEvent '.$field.' cannot be stored without changing its JSON structure or values.');
        }
        if ($preparedJson !== $type->convertToDatabaseValue($type->convertToPHPValue($check['reloaded'], $platform), $platform)) {
            throw new InvalidArgumentException('PlantEvent '.$field.' has no stable JSON representation after storage.');
        }

        // The semantic comparison above guards the shape of the caller's document.
        /** @var T $prepared */
        return $prepared;
    }

    /**
     * @param array<string, mixed>|null $payload
     * @param list<string>|null $photoUrls
     */
    private function createEvent(
        Plant $plant,
        string $eventType,
        mixed $user,
        ?array $payload,
        ?string $notes,
        ?array $photoUrls,
    ): PlantEvent {
        $lastEvent    = $this->findLastForPlant($plant->getId());
        $previousHash = $lastEvent?->getHashSelf() ?? str_repeat('0', 64);

        $event = new PlantEvent();
        $event->setPlant($plant);
        $event->setUser($user);
        $event->setEventType($eventType);
        $event->setPayload($payload);
        $event->setNotes($notes);
        $event->setPhotoUrls($photoUrls);
        $event->setTenantId($plant->getTenantId());
        $event->setHashPrevious($previousHash);
        // Store pseudonymized IP (HMAC) rather than the raw value — GDPR Art. 4.1 compliance
        $rawIp = $this->requestStack->getCurrentRequest()?->getClientIp() ?? '0.0.0.0';
        $event->setIpAddress(hash_hmac('sha256', $rawIp, $this->auditSecret));
        $event->setOccurredAt(
            new \DateTimeImmutable($event->getOccurredAt()->format('Y-m-d H:i:s'))
        );

        // Historical ordering uses seconds, with UUIDv4 only as a tie-breaker. Retain this
        // ordering compatibility until a separate technical order is designed (P1-02).
        // Concurrency is serialized by the plant lock above, never by this timestamp.
        if ($lastEvent !== null && $event->getOccurredAt() <= $lastEvent->getOccurredAt()) {
            $event->setOccurredAt($lastEvent->getOccurredAt()->modify('+1 second'));
        }

        $event->setHashSelf(
            $this->hashChainService->computeHash($event, $previousHash)
        );

        $this->getEntityManager()->persist($event);
        // wrapInTransaction flushes once, while the parent lock is still held.

        return $event;
    }

    public function findLastForPlant(Uuid $plantId): ?PlantEvent
    {
        $query = $this->createQueryBuilder('e')
            ->where('e.plant = :id')
            ->setParameter('id', $plantId, 'uuid')
            ->orderBy('e.occurredAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery();

        // appendEvent locks the parent before this read, including for an empty chain.
        return $query->getOneOrNullResult();
    }

    /** @return PlantEvent[] */
    public function findByPlantOrderedAsc(Uuid $plantId): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.plant = :id')
            ->setParameter('id', $plantId, 'uuid')
            ->orderBy('e.occurredAt', 'ASC')
            ->addOrderBy('e.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
