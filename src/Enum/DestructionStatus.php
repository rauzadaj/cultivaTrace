<?php

declare(strict_types=1);

namespace App\Enum;

enum DestructionStatus: string
{
    case Pending   = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
}
