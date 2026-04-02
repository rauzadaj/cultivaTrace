<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\User;
use App\Enum\UserAccountStatus;

final readonly class EmailVerificationService
{
    private const TOKEN_TTL = '24 hours';

    public function __construct(
        private TokenHasher $tokenHasher,
    ) {
    }

    public function issueToken(User $user, ?\DateTimeImmutable $issuedAt = null): string
    {
        $issuedAt ??= new \DateTimeImmutable();
        $plainToken = bin2hex(random_bytes(32));

        $user
            ->setAccountStatus(UserAccountStatus::PENDING_VERIFICATION)
            ->setEmailVerifiedAt(null)
            ->setEmailVerificationTokenHash($this->tokenHasher->hash($plainToken))
            ->setEmailVerificationExpiresAt($issuedAt->modify('+' . self::TOKEN_TTL));

        return $plainToken;
    }

    public function verify(User $user, string $plainToken, ?\DateTimeImmutable $now = null): bool
    {
        $now ??= new \DateTimeImmutable();
        $storedHash = $user->getEmailVerificationTokenHash() ?? str_repeat('0', 64);
        $matches = $this->tokenHasher->matches($plainToken, $storedHash);

        if (!$matches) {
            return false;
        }

        $expiresAt = $user->getEmailVerificationExpiresAt();

        if ($expiresAt === null || $expiresAt <= $now) {
            return false;
        }

        $user
            ->setAccountStatus(UserAccountStatus::ACTIVE)
            ->setEmailVerifiedAt($now)
            ->setEmailVerificationTokenHash(null)
            ->setEmailVerificationExpiresAt(null);

        return true;
    }
}
