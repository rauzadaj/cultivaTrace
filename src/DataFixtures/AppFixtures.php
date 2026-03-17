<?php

namespace App\DataFixtures;

use App\Entity\Organization;
use App\Entity\User;
use App\Enum\LicenseStatus;
use App\Enum\SubscriptionPlan;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $organizationA = (new Organization())
            ->setName('Org A')
            ->setCountry('FR')
            ->setPlan(SubscriptionPlan::PRO)
            ->setLicenseStatus(LicenseStatus::ACTIVE);

        $organizationB = (new Organization())
            ->setName('Org B')
            ->setCountry('DE')
            ->setPlan(SubscriptionPlan::STARTER)
            ->setLicenseStatus(LicenseStatus::ACTIVE);

        $manager->persist($organizationA);
        $manager->persist($organizationB);
        $manager->flush();

        $userA = (new User())
            ->setEmail('operator-a@cultivatrace.local')
            ->setRole('ROLE_OPERATOR')
            ->setRoles(['ROLE_OPERATOR'])
            ->setOrganization($organizationA);
        $userA->setPassword($this->passwordHasher->hashPassword($userA, 'demo123'));

        $userB = (new User())
            ->setEmail('operator-b@cultivatrace.local')
            ->setRole('ROLE_OPERATOR')
            ->setRoles(['ROLE_OPERATOR'])
            ->setOrganization($organizationB);
        $userB->setPassword($this->passwordHasher->hashPassword($userB, 'demo123'));

        $manager->persist($userA);
        $manager->persist($userB);
        $manager->flush();
    }
}
