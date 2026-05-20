<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Plant;
use App\Entity\PlantEvent;
use App\Service\HashChainService;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\LockMode;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;

/**
 * PlantEventRepository — APPEND-ONLY.
 *
 * RÈGLE ABSOLUE : utiliser appendEvent() pour créer un PlantEvent.
 * Ne jamais appeler EntityManager::remove() ni ::flush() sur un PlantEvent existant.
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
     * Crée et persiste un PlantEvent avec hash-chaining automatique.
     * C'est la SEULE façon de créer un PlantEvent.
     */
    public function appendEvent(
        Plant   $plant,
        string  $eventType,
        mixed   $user,
        ?array  $payload   = null,
        ?string $notes     = null,
        ?array  $photoUrls = null,
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

        // The DB stores occurredAt with second precision. Make append order deterministic
        // so hash-chain verification remains stable when multiple events are written quickly.
        if ($lastEvent !== null && $event->getOccurredAt() <= $lastEvent->getOccurredAt()) {
            $event->setOccurredAt($lastEvent->getOccurredAt()->modify('+1 second'));
        }

        $event->setHashSelf(
            $this->hashChainService->computeHash($event, $previousHash)
        );

        $this->getEntityManager()->persist($event);
        $this->getEntityManager()->flush();

        return $event;
    }

    public function findLastForPlant(Uuid $plantId): ?PlantEvent
    {
        return $this->createQueryBuilder('e')
            ->where('e.plant = :id')
            ->setParameter('id', $plantId, 'uuid')
            ->orderBy('e.occurredAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();
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
