<?php

declare(strict_types=1);

namespace App\Service\Auth;

final class TokenHasher
{
    public function hash(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    public function matches(string $plainToken, string $hashedToken): bool
    {
        return hash_equals($hashedToken, $this->hash($plainToken));
    }
}
