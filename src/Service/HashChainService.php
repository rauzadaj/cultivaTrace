<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PlantEvent;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;

/**
 * HashChainService — garantit l'intégrité de l'audit trail.
 *
 * Chaque PlantEvent a un hashSelf calculé ainsi :
 *   hashSelf = HMAC-SHA256(id|JSON(payload)|occurredAt|notes|JSON(photoUrls)|ipAddress|hashPrevious, AUDIT_CHAIN_SECRET)
 *
 * L'utilisation de HMAC avec une clé secrète (hors DB) empêche un admin DB
 * de recalculer les hashes après modification — contrairement au SHA-256 pur.
 *
 * Le premier event d'un plant a hashPrevious = "0000...0000" (64 zéros).
 *
 * Utilisation :
 *   $service->computeHash($event, $previousHash)  // calcule le hash
 *   $service->verify($plantId)                    // vérifie toute la chaîne
 */
class HashChainService
{
    public function __construct(
        private readonly ManagerRegistry $registry,
        #[Autowire('%env(AUDIT_CHAIN_SECRET)%')]
        private readonly string $auditSecret,
    ) {}

    /**
     * Calcule le hashSelf d'un event via HMAC-SHA256.
     * À appeler AVANT de persister l'event.
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
     * Vérifie l'intégrité de toute la chaîne d'events d'un plant.
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
