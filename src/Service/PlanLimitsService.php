<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Organization;
use App\Enum\PlantStatus;
use App\Enum\SubscriptionPlan;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Types\UuidType;

/**
 * PlanLimitsService — vérifie les limites du plan avant chaque création.
 *
 * Appelé depuis les State Processors API Platform ou les Controllers.
 * Les limites sont définies dans l'enum SubscriptionPlan.
 *
 * Retourne une exception PlanLimitExceededException si la limite est atteinte.
 * Le controller ou le processor retourne alors HTTP 402 avec un message d'upgrade.
 */
class PlanLimitsService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Vérifie si l'organisation peut créer un nouveau plant.
     *
     * @throws PlanLimitExceededException
     */
    public function checkPlantLimit(Organization $org): void
    {
        $max     = $org->getPlan()->maxPlants();
        $current = $this->countActivePlants($org);

        if ($current >= $max) {
            throw new PlanLimitExceededException(
                sprintf(
                    'Limite de plants atteinte (%d/%d). Passez au plan supérieur pour ajouter plus de plants.',
                    $current,
                    $max
                ),
                'plants',
                $current,
                $max,
                $this->getUpgradePlan($org->getPlan())
            );
        }
    }

    /**
     * Vérifie si l'organisation peut créer une nouvelle salle.
     *
     * @throws PlanLimitExceededException
     */
    public function checkRoomLimit(Organization $org): void
    {
        $max     = $org->getPlan()->maxRooms();
        $current = $this->countRooms($org);

        if ($current >= $max) {
            throw new PlanLimitExceededException(
                sprintf(
                    'Limite de salles atteinte (%d/%d). Passez au plan supérieur.',
                    $current,
                    $max
                ),
                'rooms',
                $current,
                $max,
                $this->getUpgradePlan($org->getPlan())
            );
        }
    }

    /**
     * Vérifie si l'organisation peut ajouter un utilisateur.
     *
     * @throws PlanLimitExceededException
     */
    public function checkUserLimit(Organization $org): void
    {
        $max     = $org->getPlan()->maxUsers();
        $current = $this->countUsers($org);

        if ($current >= $max) {
            throw new PlanLimitExceededException(
                sprintf(
                    'Limite d\'utilisateurs atteinte (%d/%d). Passez au plan supérieur.',
                    $current,
                    $max
                ),
                'users',
                $current,
                $max,
                $this->getUpgradePlan($org->getPlan())
            );
        }
    }

    /**
     * Vérifie si l'organisation a accès à l'IoT (plan Pro+).
     *
     * @throws PlanLimitExceededException
     */
    public function checkIoTAccess(Organization $org): void
    {
        if (!$org->getPlan()->hasIoT()) {
            throw new PlanLimitExceededException(
                'L\'accès aux capteurs IoT nécessite le plan Pro ou supérieur.',
                'iot',
                0,
                0,
                SubscriptionPlan::PRO
            );
        }
    }

    /**
     * Retourne les limites actuelles de l'organisation.
     */
    public function getLimits(Organization $org): array
    {
        $plan           = $org->getPlan();
        $activePlants   = $this->countActivePlants($org);
        $rooms          = $this->countRooms($org);
        $users          = $this->countUsers($org);

        return [
            'plan'   => $plan->value,
            'plants' => [
                'current' => $activePlants,
                'max'     => $plan->maxPlants() === PHP_INT_MAX ? null : $plan->maxPlants(),
                'reached' => $activePlants >= $plan->maxPlants(),
            ],
            'rooms'  => [
                'current' => $rooms,
                'max'     => $plan->maxRooms() === PHP_INT_MAX ? null : $plan->maxRooms(),
                'reached' => $rooms >= $plan->maxRooms(),
            ],
            'users'  => [
                'current' => $users,
                'max'     => $plan->maxUsers() === PHP_INT_MAX ? null : $plan->maxUsers(),
                'reached' => $users >= $plan->maxUsers(),
            ],
            'iot'    => [
                'available' => $plan->hasIoT(),
            ],
        ];
    }

    private function countUsers(Organization $org): int
    {
        return (int) $this->em->createQuery(
            'SELECT COUNT(u.id) FROM App\Entity\User u WHERE u.organization = :org'
        )
        ->setParameter('org', $org->getId(), 'uuid')
        ->getSingleScalarResult();
    }

    private function countActivePlants(Organization $org): int
    {
        // Count all plants ever created for the tenant regardless of status —
        // prevents quota bypass via archive + recreate cycle.
        return (int) $this->em->createQuery(
            'SELECT COUNT(p.id) FROM App\Entity\Plant p
             WHERE p.tenantId = :tenantId'
        )
        ->setParameter('tenantId', $org->getId(), 'uuid')
        ->getSingleScalarResult();
    }

    private function countRooms(Organization $org): int
    {
        return (int) $this->em->createQuery(
            'SELECT COUNT(r.id) FROM App\Entity\Room r
             INNER JOIN r.farm f
             WHERE f.organization = :org'
        )
        ->setParameter('org', $org->getId(), UuidType::NAME)
        ->getSingleScalarResult();
    }

    private function getUpgradePlan(SubscriptionPlan $current): SubscriptionPlan
    {
        return match ($current) {
            SubscriptionPlan::STARTER  => SubscriptionPlan::PRO,
            SubscriptionPlan::PRO      => SubscriptionPlan::BUSINESS,
            SubscriptionPlan::BUSINESS => SubscriptionPlan::ENTERPRISE,
            default                    => SubscriptionPlan::ENTERPRISE,
        };
    }
}
