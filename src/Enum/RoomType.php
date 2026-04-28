<?php

declare(strict_types=1);

namespace App\Enum;

enum RoomType: string
{
    case Veg     = 'veg';
    case Flower  = 'flower';
    case Drying  = 'drying';
    case Clone   = 'clone';
    case Mixed   = 'mixed';
}
