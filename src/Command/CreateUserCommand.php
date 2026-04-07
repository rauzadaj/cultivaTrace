<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Organization;
use App\Entity\User;
use App\Enum\LicenseStatus;
use App\Enum\SubscriptionPlan;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-user',
    description: 'Create a user and attach it to an organization.',
)]
final class CreateUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'User email.')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'User password.')
            ->addOption('organization', null, InputOption::VALUE_REQUIRED, 'Organization name.')
            ->addOption('role', null, InputOption::VALUE_OPTIONAL, 'Primary role.', 'ROLE_ORG_USER')
            ->addOption('country', null, InputOption::VALUE_OPTIONAL, 'Organization country.', 'FR');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = (string) $input->getOption('email');
        $password = (string) $input->getOption('password');
        $organizationName = (string) $input->getOption('organization');
        $role = strtoupper((string) $input->getOption('role'));
        $country = strtoupper((string) $input->getOption('country'));
        $allowedRoles = ['ROLE_ORG_USER', 'ROLE_ORG_ADMIN', 'ROLE_SUPER_ADMIN', 'ROLE_API'];

        if ($email === '' || $password === '' || $organizationName === '') {
            $io->error('The options --email, --password and --organization are required.');

            return Command::INVALID;
        }

        if (!in_array($role, $allowedRoles, true)) {
            $io->error(sprintf(
                'Invalid role "%s". Allowed roles: %s.',
                $role,
                implode(', ', $allowedRoles),
            ));

            return Command::INVALID;
        }

        /** @var User|null $existingUser */
        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingUser instanceof User) {
            $io->error(sprintf('A user with email "%s" already exists.', $email));

            return Command::FAILURE;
        }

        /** @var Organization|null $organization */
        $organization = $this->entityManager->getRepository(Organization::class)->findOneBy(['name' => $organizationName]);
        if (!$organization instanceof Organization) {
            $organization = (new Organization())
                ->setName($organizationName)
                ->setCountry($country)
                ->setPlan(SubscriptionPlan::STARTER)
                ->setLicenseStatus(LicenseStatus::PENDING);

            $this->entityManager->persist($organization);
        }

        $user = (new User())
            ->setEmail($email)
            ->setRoles([$role])
            ->setOrganization($organization);

        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf(
            'User "%s" created in organization "%s".',
            $email,
            $organization->getName(),
        ));

        return Command::SUCCESS;
    }
}
