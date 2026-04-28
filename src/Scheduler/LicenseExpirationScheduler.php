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
 * LicenseExpirationScheduler — vérifie quotidiennement les licences qui expirent.
 *
 * Alertes envoyées :
 *   J-30 : "Votre licence expire dans 30 jours"
 *   J-7  : "Votre licence expire dans 7 jours — Action urgente"
 *   J0   : Passage en status "suspended" automatique
 *
 * Exécution : tous les jours à 8h00
 *
 * Prérequis : symfony/scheduler (inclus dans Symfony 6.3+)
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
        $this->logger->info('[LicenseScheduler] Vérification des expirations de licences');

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
                // Suspension automatique
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
            '[LicenseScheduler] Terminé — J-30: %d, J-7: %d, Suspendus: %d',
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
                "⚠️ Votre licence CannaSaaS expire dans {$daysLeft} jours",
                "Votre licence expire le {$org->getLicenseExpiresAt()->format('d/m/Y')}.\n\n" .
                "Renouvelez votre licence pour maintenir votre accès à CannaSaaS.\n" .
                "Connectez-vous sur votre espace : https://app.cannas.app/settings/license",
            ],
            'urgent' => [
                "🚨 URGENT — Votre licence CannaSaaS expire dans {$daysLeft} jour(s)",
                "ATTENTION : Votre licence expire dans {$daysLeft} jour(s) ({$org->getLicenseExpiresAt()->format('d/m/Y')}).\n\n" .
                "Sans renouvellement, votre accès sera automatiquement suspendu.\n" .
                "Renouvelez maintenant : https://app.cannas.app/settings/license",
            ],
            'expired' => [
                "❌ Votre licence CannaSaaS a expiré — Accès suspendu",
                "Votre licence a expiré. Votre accès a été suspendu.\n\n" .
                "Pour réactiver votre compte, soumettez une nouvelle licence valide :\n" .
                "https://app.cannas.app/kyb\n\n" .
                "Besoin d'aide ? Contactez support@cannas.app",
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
            $this->logger->error('[LicenseScheduler] Erreur email: ' . $e->getMessage());
        }
    }
}
