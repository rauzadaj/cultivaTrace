<?php

namespace App\Tests\Fixture;

use App\Entity\Farm;
use App\Entity\Organization;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class TenantIsolationFixtures
{
    public static function load(EntityManagerInterface $entityManager): TenantIsolationFixtureSet
    {
        $organizationA = (new Organization())
            ->setName('Org A')
            ->setCountry('FR');
        $organizationB = (new Organization())
            ->setName('Org B')
            ->setCountry('DE');

        $entityManager->persist($organizationA);
        $entityManager->persist($organizationB);
        $entityManager->flush();

        $userA = (new User())
            ->setEmail('operator-a@cultivatrace.local')
            ->setPassword('hashed-password-a')
            ->setRole('ROLE_OPERATOR')
            ->setOrganization($organizationA);
        $userB = (new User())
            ->setEmail('operator-b@cultivatrace.local')
            ->setPassword('hashed-password-b')
            ->setRole('ROLE_OPERATOR')
            ->setOrganization($organizationB);

        $farmA = (new Farm())
            ->setOrganization($organizationA)
            ->setTenantId($organizationA->getId())
            ->setName('Farm A');
        $farmB = (new Farm())
            ->setOrganization($organizationB)
            ->setTenantId($organizationB->getId())
            ->setName('Farm B');

        $entityManager->persist($userA);
        $entityManager->persist($userB);
        $entityManager->persist($farmA);
        $entityManager->persist($farmB);
        $entityManager->flush();
        $entityManager->clear();

        /** @var Organization $organizationA */
        $organizationA = $entityManager->getRepository(Organization::class)->findOneBy(['name' => 'Org A']);
        /** @var Organization $organizationB */
        $organizationB = $entityManager->getRepository(Organization::class)->findOneBy(['name' => 'Org B']);
        /** @var User $userA */
        $userA = $entityManager->getRepository(User::class)->findOneBy(['email' => 'operator-a@cultivatrace.local']);
        /** @var User $userB */
        $userB = $entityManager->getRepository(User::class)->findOneBy(['email' => 'operator-b@cultivatrace.local']);
        /** @var Farm $farmA */
        $farmA = $entityManager->getRepository(Farm::class)->findOneBy(['name' => 'Farm A']);
        /** @var Farm $farmB */
        $farmB = $entityManager->getRepository(Farm::class)->findOneBy(['name' => 'Farm B']);

        return new TenantIsolationFixtureSet(
            $organizationA,
            $organizationB,
            $userA,
            $userB,
            $farmA,
            $farmB,
        );
    }
}
