<?php

namespace App\Enum;

enum LicenseStatus: string
{
    case PENDING   = 'pending';
    case ACTIVE    = 'active';
    case SUSPENDED = 'suspended';
    case EXPIRED   = 'expired';
    case REJECTED  = 'rejected';
}
