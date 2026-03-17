<?php

namespace App\Enum;

enum PlantStage: string
{
    case GERMINATION = 'germination';
    case VEGETATION  = 'vegetation';
    case FLOWERING   = 'flowering';
    case HARVEST     = 'harvest';
    case ARCHIVED    = 'archived';

    public function label(): string
    {
        return match($this) {
            self::GERMINATION => 'Germination',
            self::VEGETATION  => 'Végétation',
            self::FLOWERING   => 'Floraison',
            self::HARVEST     => 'Récolte',
            self::ARCHIVED    => 'Archivé',
        };
    }

    public function next(): ?self
    {
        return match($this) {
            self::GERMINATION => self::VEGETATION,
            self::VEGETATION  => self::FLOWERING,
            self::FLOWERING   => self::HARVEST,
            self::HARVEST     => self::ARCHIVED,
            self::ARCHIVED    => null,
        };
    }
}
