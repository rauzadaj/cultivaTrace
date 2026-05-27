<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Entity\Organization;
use App\Entity\User;
use App\Enum\LicenseStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Scheduler\Attribute\AsCronTask;
use Psr\Log\LoggerInterface;

/**
 * LicenseExpirationScheduler — checks daily for expiring licenses.
 *
 * Alerts sent:
 *   D-30 : "Your license expires in 30 days"
 *   D-7  : "Your license expires in 7 days — Urgent action required"
 *   D0   : Automatic status change to "suspended"
 *
 * Schedule: every day at 08:00
 *
 * Requirement: symfony/scheduler (included in Symfony 6.3+)
 */
#[AsCronTask('0 8 * * *', method: 'checkExpirations')]
class LicenseExpirationScheduler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly string $alertFromEmail = 'alerts@cannas.app',
    ) {}

    public function checkExpirations(): void
    {
        $this->logger->info('[LicenseScheduler] Checking license expirations');

        $now   = new \DateTimeImmutable();
        $orgs  = $this->em->getRepository(Organization::class)->findAll();
        $count = ['alerted_30' => 0, 'alerted_7' => 0, 'suspended' => 0];

        foreach ($orgs as $org) {
            if ($org->getLicenseStatus() === LicenseStatus::SUSPENDED) continue;
            if ($org->getLicenseExpiresAt() === null) continue;

            $expiresAt  = $org->getLicenseExpiresAt();
            $daysLeft   = (int) $now->diff($expiresAt)->days;
            $isPast     = $expiresAt < $now;

            if ($isPast) {
                $org->setLicenseStatus(LicenseStatus::EXPIRED);
                $this->sendAlert($org, 0, 'expired');
                $count['suspended']++;

            } elseif ($daysLeft <= 7) {
                $this->sendAlert($org, $daysLeft, 'urgent');
                $count['alerted_7']++;

            } elseif ($daysLeft <= 30) {
                $this->sendAlert($org, $daysLeft, 'warning');
                $count['alerted_30']++;
            }
        }

        $this->em->flush();

        $this->logger->info(sprintf(
            '[LicenseScheduler] Done — D-30: %d, D-7: %d, Suspended: %d',
            $count['alerted_30'],
            $count['alerted_7'],
            $count['suspended']
        ));
    }

    private function sendAlert(Organization $org, int $daysLeft, string $type): void
    {
        $firstUser = $org->getUsers()->first();
        $userEmail = $firstUser instanceof User ? $firstUser->getEmail() : null;
        if (!$userEmail) return;

        [$subject, $body] = match ($type) {
            'warning' => [
                "⚠️ Your CannaSaaS license expires in {$daysLeft} days",
                "Your license expires on {$org->getLicenseExpiresAt()->format('Y-m-d')}.\n\n" .
                "Renew your license to maintain your access to CannaSaaS.\n" .
                "Sign in to your account: https://app.cannas.app/settings/license",
            ],
            'urgent' => [
                "🚨 URGENT — Your CannaSaaS license expires in {$daysLeft} day(s)",
                "WARNING: Your license expires in {$daysLeft} day(s) ({$org->getLicenseExpiresAt()->format('Y-m-d')}).\n\n" .
                "Without renewal, your access will be automatically suspended.\n" .
                "Renew now: https://app.cannas.app/settings/license",
            ],
            'expired' => [
                "❌ Your CannaSaaS license has expired — Access suspended",
                "Your license has expired. Your access has been suspended.\n\n" .
                "To reactivate your account, submit a new valid license:\n" .
                "https://app.cannas.app/kyb\n\n" .
                "Need help? Contact support@cannas.app",
            ],
            default => ['', ''],
        };

        if (!$subject) return;

        try {
            $email = (new Email())
                ->from($this->alertFromEmail)
                ->to($userEmail)
                ->subject($subject)
                ->text($body);
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger->error('[LicenseScheduler] Email error: ' . $e->getMessage());
        }
    }
}
