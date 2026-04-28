<?php

declare(strict_types=1);

namespace App\Domain\Plant;

enum PlantStatus: string
{
    case GERMINATION = 'germination';
    case VEGETATIVE = 'vegetative';
    case FLOWERING = 'flowering';
    case HARVESTED = 'harvested';
    case DESTROYED = 'destroyed';
    case QUARANTINE = 'quarantine';
}
