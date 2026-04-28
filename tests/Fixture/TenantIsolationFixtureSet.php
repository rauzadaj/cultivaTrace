<?php

namespace App\Tests\Fixture;

use App\Entity\Farm;
use App\Entity\Organization;
use App\Entity\User;

final readonly class TenantIsolationFixtureSet
{
    public function __construct(
        public Organization $organizationA,
        public Organization $organizationB,
        public User $userA,
        public User $userB,
        public Farm $farmA,
        public Farm $farmB,
    ) {
    }
}
