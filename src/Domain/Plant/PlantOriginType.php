<?php

declare(strict_types=1);

namespace App\Domain\Plant;

enum PlantOriginType: string
{
    case SEED = 'SEED';
    case CLONE = 'CLONE';
    case TISSUE = 'TISSUE';
}
