<?php

namespace App\Enum;

enum SubscriptionPlan: string
{
    case GROWTH     = 'growth';
    case PRO        = 'pro';
    case SCALE      = 'scale';
    case ENTERPRISE = 'enterprise';

    public function maxPlants(): int
    {
        return match($this) {
            self::GROWTH     => 500,
            self::PRO        => 2500,
            self::SCALE      => PHP_INT_MAX,
            self::ENTERPRISE => PHP_INT_MAX,
        };
    }

    /** Maximum number of farms (sites). */
    public function maxFarms(): int
    {
        return match($this) {
            self::GROWTH     => 1,
            self::PRO        => 3,
            self::SCALE      => PHP_INT_MAX,
            self::ENTERPRISE => PHP_INT_MAX,
        };
    }

    public function maxRooms(): int
    {
        return PHP_INT_MAX;
    }

    public function maxUsers(): int
    {
        return PHP_INT_MAX;
    }

    public function hasIoT(): bool
    {
        return true;
    }
}
