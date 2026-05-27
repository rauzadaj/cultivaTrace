<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\SubscriptionPlan;

/**
 * Exception thrown when a plan limit is reached.
 * Caught by the controller to return HTTP 402.
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
