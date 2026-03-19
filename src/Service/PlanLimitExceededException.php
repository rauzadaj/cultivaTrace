<?php

namespace App\Service;

use App\Enum\SubscriptionPlan;

/**
 * Exception lancée quand une limite de plan est atteinte.
 * Interceptée par le controller pour retourner HTTP 402.
 */
class PlanLimitExceededException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $limitType,
        public readonly int $current,
        public readonly int $max,
        public readonly SubscriptionPlan $upgradeTo,
    ) {
        parent::__construct($message);
    }

    public function toArray(): array
    {
        return [
            'error'      => $this->getMessage(),
            'limitType'  => $this->limitType,
            'current'    => $this->current,
            'max'        => $this->max === PHP_INT_MAX ? null : $this->max,
            'upgradeTo'  => $this->upgradeTo->value,
            'upgradeUrl' => '/billing/upgrade',
        ];
    }
}
