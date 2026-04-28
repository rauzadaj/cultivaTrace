<?php

namespace App\Domain\Cultivation\Enum;

enum CropStage: string
{
    case Seedling = 'seedling';
    case Veg = 'veg';
    case Flower = 'flower';
    case Harvest = 'harvest';
}
