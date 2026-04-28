<?php

declare(strict_types=1);

namespace App\Domain\Plant;

use Symfony\Component\Uid\Uuid;

interface PlantRepositoryInterface
{
    /** @return list<Plant> */
    public function findByOrganization(Uuid $orgId): array;

    public function findById(Uuid $id, Uuid $orgId): ?Plant;
}
