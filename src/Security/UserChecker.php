<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Enum\UserAccountStatus;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if ($user->getAccountStatus() !== UserAccountStatus::ACTIVE) {
            throw new CustomUserMessageAccountStatusException('Invalid credentials.');
        }

        if ($user->getOrganization() === null || $user->getOrganization()->isSuspended()) {
            throw new CustomUserMessageAccountStatusException('Invalid credentials.');
        }
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        $this->checkPreAuth($user);
    }
}
