<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

final class TenantAwareVoter extends Voter
{
    public const ACCESS = 'TENANT_ACCESS';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::ACCESS && \is_object($subject);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        if (\in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        if (!$user->hasOrganization()) {
            return false;
        }

        $organization = $user->getOrganization();

        if (!method_exists($subject, 'getTenantId')) {
            return false;
        }

        $subjectTenantId = $subject->getTenantId();
        if ($subjectTenantId === null) {
            return false;
        }

        return (string) $subjectTenantId === (string) $organization->getId();
    }
}
