<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Organization;
use App\Entity\User;
use App\Enum\LicenseStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[AsCommand(
    name: 'app:check-expired-licenses',
    description: 'Mark organizations with a past licenseExpiresAt as EXPIRED and notify them.',
)]
final class CheckExpiredLicensesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
        private readonly string $alertFromEmail = 'alerts@cannas.app',
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview without making changes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $now = new \DateTimeImmutable();

        /** @var Organization[] $expired */
        $expired = $this->entityManager->createQueryBuilder()
            ->select('o')
            ->from(Organization::class, 'o')
            ->where('o.licenseStatus = :active')
            ->andWhere('o.licenseExpiresAt IS NOT NULL')
            ->andWhere('o.licenseExpiresAt < :now')
            ->setParameter('active', LicenseStatus::ACTIVE)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();

        if ($expired === []) {
            $io->success('No expired licenses found.');
            return Command::SUCCESS;
        }

        $io->note(sprintf('Found %d organization(s) with expired licenses.', count($expired)));

        foreach ($expired as $org) {
            $io->writeln(sprintf(
                '  [%s] %s — expired %s',
                $org->getId(),
                $org->getName(),
                $org->getLicenseExpiresAt()?->format('Y-m-d') ?? 'N/A',
            ));

            if ($dryRun) {
                continue;
            }

            $org->setLicenseStatus(LicenseStatus::EXPIRED);
            $this->notifyOrganization($org);
        }

        if (!$dryRun) {
            $this->entityManager->flush();
            $io->success(sprintf('%d license(s) marked as EXPIRED.', count($expired)));
        } else {
            $io->comment('Dry-run mode — no changes written.');
        }

        return Command::SUCCESS;
    }

    private function notifyOrganization(Organization $org): void
    {
        $firstUser = $org->getUsers()->first();
        if (!$firstUser instanceof User) {
            return;
        }

        try {
            $email = (new Email())
                ->from($this->alertFromEmail)
                ->to($firstUser->getEmail())
                ->subject('⚠️ Your CultivaTrace license has expired')
                ->text(sprintf(
                    "Hello,\n\n" .
                    "The license for your organization \"%s\" expired on %s.\n\n" .
                    "Your access to CultivaTrace is suspended until you renew your license.\n\n" .
                    "Contact us at jonathan@rauzada.me to renew your license.\n\n" .
                    "The CultivaTrace Team",
                    $org->getName(),
                    $org->getLicenseExpiresAt()?->format('Y-m-d') ?? 'N/A',
                ));
            $this->mailer->send($email);
        } catch (\Throwable) {
            // Mail failure must not abort the expiry sweep
        }
    }
}
