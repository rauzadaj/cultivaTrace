<?php

namespace App\Enum;

enum PlantStatus: string
{
    case ACTIVE    = 'active';
    case HARVESTED = 'harvested';
    case DESTROYED = 'destroyed';
    case ARCHIVED  = 'archived';
}
