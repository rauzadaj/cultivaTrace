<?php

declare(strict_types=1);

namespace App\Service\License;

use App\Entity\Organization;
use App\Enum\LicenseStatus;
use App\Exception\LicenseNotApprovedException;

final readonly class LicenseGuard
{
    public function assertLicenseApproved(Organization $tenant): void
    {
        $this->assertCanWrite($tenant);
    }

    public function assertCanWrite(Organization $tenant): void
    {
        $status = $tenant->getLicenseStatus();

        if ($status !== LicenseStatus::ACTIVE) {
            throw new LicenseNotApprovedException($status->value);
        }
    }
}
