<?php

namespace App\Service;

use App\Entity\PlantEvent;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * HashChainService — garantit l'intégrité de l'audit trail.
 *
 * Chaque PlantEvent a un hashSelf calculé ainsi :
 *   hashSelf = SHA-256(id + JSON(payload) + occurredAt.timestamp + hashPrevious)
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
    ) {}

    /**
     * Calcule le hashSelf d'un event.
     * À appeler AVANT de persister l'event.
     */
    public function computeHash(PlantEvent $event, string $hashPrevious): string
    {
        $data = implode('|', [
            (string) $event->getId(),
            json_encode($event->getPayload(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            $event->getOccurredAt()->format('U'), // timestamp Unix
            $hashPrevious,
        ]);

        return hash('sha256', $data);
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

            if ($event->getHashSelf() !== $expectedHash) {
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
