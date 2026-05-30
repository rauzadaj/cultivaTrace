<?php

namespace App\Security\Voter;

use App\Entity\Plant;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

class PlantVoter extends Voter
{
    public const VIEW    = 'PLANT_VIEW';
    public const CREATE  = 'PLANT_CREATE';
    public const EDIT    = 'PLANT_EDIT';
    public const HARVEST = 'PLANT_HARVEST';
    public const DESTROY = 'PLANT_DESTROY';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
            self::VIEW, self::CREATE, self::EDIT,
            self::HARVEST, self::DESTROY,
        ], true) && ($subject instanceof Plant || $subject === null);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) return false;
        if ($user->hasOrganization() && $user->getOrganization()->isSuspended()) return false;

        return match ($attribute) {
            self::VIEW    => $this->hasAnyRole($user, ['ROLE_VIEWER', 'ROLE_ORG_USER', 'ROLE_ORG_ADMIN', 'ROLE_SUPER_ADMIN']),
            self::CREATE  => $this->hasAnyRole($user, ['ROLE_ORG_USER', 'ROLE_ORG_ADMIN', 'ROLE_SUPER_ADMIN']),
            self::EDIT    => $this->hasAnyRole($user, ['ROLE_ORG_USER', 'ROLE_ORG_ADMIN', 'ROLE_SUPER_ADMIN']),
            self::HARVEST => $this->hasAnyRole($user, ['ROLE_ORG_USER', 'ROLE_ORG_ADMIN', 'ROLE_SUPER_ADMIN']),
            self::DESTROY => $this->hasAnyRole($user, ['ROLE_ORG_ADMIN', 'ROLE_SUPER_ADMIN']),
            default       => false,
        };
    }

    private function hasAnyRole(User $user, array $roles): bool
    {
        foreach ($roles as $role) {
            if (in_array($role, $user->getRoles(), true)) return true;
        }
        return false;
    }
}
