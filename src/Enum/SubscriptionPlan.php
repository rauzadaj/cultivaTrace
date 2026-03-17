<?php

namespace App\Enum;

enum SubscriptionPlan: string
{
    case STARTER    = 'starter';
    case PRO        = 'pro';
    case BUSINESS   = 'business';
    case ENTERPRISE = 'enterprise';

    public function maxPlants(): int
    {
        return match($this) {
            self::STARTER    => 200,
            self::PRO        => 1500,
            self::BUSINESS   => PHP_INT_MAX,
            self::ENTERPRISE => PHP_INT_MAX,
        };
    }

    public function maxRooms(): int
    {
        return match($this) {
            self::STARTER    => 2,
            self::PRO        => 10,
            self::BUSINESS   => PHP_INT_MAX,
            self::ENTERPRISE => PHP_INT_MAX,
        };
    }

    public function maxUsers(): int
    {
        return match($this) {
            self::STARTER    => 3,
            self::PRO        => 15,
            self::BUSINESS   => PHP_INT_MAX,
            self::ENTERPRISE => PHP_INT_MAX,
        };
    }

    public function hasIoT(): bool
    {
        return match($this) {
            self::STARTER    => false,
            default          => true,
        };
    }
}
