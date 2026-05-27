<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PlantEvent;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;

/**
 * HashChainService — guarantees the integrity of the audit trail.
 *
 * Each PlantEvent has a hashSelf computed as:
 *   hashSelf = HMAC-SHA256(id|JSON(payload)|occurredAt|notes|JSON(photoUrls)|ipAddress|hashPrevious, AUDIT_CHAIN_SECRET)
 *
 * Using HMAC with a secret key (outside the DB) prevents a DB admin
 * from recomputing hashes after modification — unlike plain SHA-256.
 *
 * The first event of a plant has hashPrevious = "0000...0000" (64 zeros).
 *
 * Usage:
 *   $service->computeHash($event, $previousHash)  // computes the hash
 *   $service->verify($plantId)                    // verifies the entire chain
 */
class HashChainService
{
    public function __construct(
        private readonly ManagerRegistry $registry,
        #[Autowire('%env(AUDIT_CHAIN_SECRET)%')]
        private readonly string $auditSecret,
    ) {}

    /**
     * Computes the hashSelf of an event via HMAC-SHA256.
     * Must be called BEFORE persisting the event.
     */
    public function computeHash(PlantEvent $event, string $hashPrevious): string
    {
        $data = implode('|', [
            (string) $event->getId(),
            json_encode($event->getPayload(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            $event->getOccurredAt()->format('U'),
            $event->getNotes() ?? '',
            json_encode($event->getPhotoUrls() ?? [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            $event->getIpAddress(),
            $hashPrevious,
        ]);

        return hash_hmac('sha256', $data, $this->auditSecret);
    }

    /**
     * Verifies the integrity of the entire event chain for a plant.
     *
     * @return array{valid: bool, broken_at: string|null, checked: int}
     */
    public function verify(Uuid $plantId): array
    {
        $events = $this->registry
            ->getRepository(PlantEvent::class)
            ->findByPlantOrderedAsc($plantId);

        $previousHash = str_repeat('0', 64);
        $checked = 0;

        foreach ($events as $event) {
            $expectedHash = $this->computeHash($event, $previousHash);

            if (!hash_equals($event->getHashSelf(), $expectedHash)) {
                return [
                    'valid'      => false,
                    'broken_at'  => (string) $event->getId(),
                    'checked'    => $checked,
                ];
            }

            $previousHash = $event->getHashSelf();
            $checked++;
        }

        return [
            'valid'     => true,
            'broken_at' => null,
            'checked'   => $checked,
        ];
    }
}
