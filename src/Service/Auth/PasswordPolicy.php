<?php

declare(strict_types=1);

namespace App\Service\Auth;

use InvalidArgumentException;

final class PasswordPolicy
{
    public function assertValid(string $plainPassword): void
    {
        if (mb_strlen($plainPassword) < 12) {
            throw new InvalidArgumentException('Password must contain at least 12 characters.');
        }

        if (1 !== preg_match('/[A-Z]/', $plainPassword)) {
            throw new InvalidArgumentException('Password must contain at least one uppercase letter.');
        }

        if (1 !== preg_match('/\d/', $plainPassword)) {
            throw new InvalidArgumentException('Password must contain at least one number.');
        }

        if (1 !== preg_match('/[^a-zA-Z0-9]/', $plainPassword)) {
            throw new InvalidArgumentException('Password must contain at least one special character.');
        }
    }
}
