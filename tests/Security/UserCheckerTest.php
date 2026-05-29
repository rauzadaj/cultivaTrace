<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\Organization;
use App\Entity\User;
use App\Enum\UserAccountStatus;
use App\Security\UserChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

final class UserCheckerTest extends TestCase
{
    public function testItRejectsAuthenticatedUsersWithoutOrganization(): void
    {
        $user = (new User())
            ->setEmail('orphan@cultivatrace.local')
            ->setRoles(['ROLE_ORG_USER'])
            ->setPassword('hashed-password')
            ->setAccountStatus(UserAccountStatus::ACTIVE);

        $reflection = new \ReflectionProperty($user, 'organization');
        $reflection->setValue($user, null);

        $checker = new UserChecker();

        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage('Invalid credentials.');

        $checker->checkPreAuth($user);
    }

    public function testItAcceptsActiveUserWithOrganization(): void
    {
        $organization = (new Organization())
            ->setName('CultivaTrace')
            ->setCountry('FR');
        $user = (new User())
            ->setEmail('member@cultivatrace.local')
            ->setRoles(['ROLE_ORG_USER'])
            ->setPassword('hashed-password')
            ->setOrganization($organization)
            ->setAccountStatus(UserAccountStatus::ACTIVE);

        $checker = new UserChecker();

        // An active user with an organization must pass pre-auth without raising
        // any AccountStatusException.
        $this->expectNotToPerformAssertions();

        $checker->checkPreAuth($user);
    }
}
