<?php

declare(strict_types=1);

namespace App\Enum;

enum UserAccountStatus: string
{
    case PENDING_VERIFICATION = 'pending_verification';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
}
