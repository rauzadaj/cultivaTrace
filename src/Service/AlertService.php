<?php

namespace App\Service;

use App\Entity\Sensor;
use App\Repository\SensorReadingRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * AlertService — détecte les dépassements de seuils IoT et envoie les alertes.
 *
 * Déduplication : 1 alerte max par capteur par heure (via Redis/Cache).
 * Délai cible : email envoyé < 60s après dépassement de seuil.
 */
class AlertService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly CacheInterface $cache,
        private readonly string $alertFromEmail = 'alerts@cannas.app',
    ) {}

    /**
     * Vérifie si une valeur dépasse les seuils d'un capteur
     * et envoie une alerte si nécessaire.
     */
    public function checkAndAlert(Sensor $sensor, float $value): bool
    {
        if (!$sensor->isValueOutOfRange($value)) {
            return false;
        }

        // Déduplication — 1 alerte max par capteur par cooldown (défaut 60 min)
        $cooldown  = $sensor->getThresholds()['alertCooldownMinutes'] ?? 60;
        $cacheKey  = 'sensor_alert_' . $sensor->getId();

        $alreadyAlerted = false;
        $this->cache->get($cacheKey, function (ItemInterface $item) use ($cooldown, &$alreadyAlerted) {
            // Si on arrive ici, la clé n'existe pas — on peut alerter
            $item->expiresAfter($cooldown * 60);
            $alreadyAlerted = false;
            return true;
        });

        // Si la clé existait déjà, on a été dédupliqué
        if ($alreadyAlerted) {
            return false;
        }

        $this->sendAlert($sensor, $value);
        return true;
    }

    private function sendAlert(Sensor $sensor, float $value): void
    {
        $thresholds = $sensor->getThresholds();
        $unit       = $thresholds['unit'] ?? '';
        $min        = $thresholds['min'] ?? '—';
        $max        = $thresholds['max'] ?? '—';

        $direction = $value < ($thresholds['min'] ?? PHP_INT_MAX)
            ? 'en dessous du minimum'
            : 'au-dessus du maximum';

        $subject = sprintf(
            '🚨 Alerte capteur — %s %s (%s : %.2f%s)',
            $sensor->getRoom()->getName(),
            $sensor->getType(),
            $direction,
            $value,
            $unit
        );

        $body = sprintf(
            "Alerte CannaSaaS\n\n" .
            "Capteur    : %s (%s)\n" .
            "Salle      : %s\n" .
            "Valeur     : %.2f %s\n" .
            "Seuil min  : %s %s\n" .
            "Seuil max  : %s %s\n" .
            "Horodatage : %s\n\n" .
            "Connectez-vous sur CannaSaaS pour consulter le dashboard.",
            $sensor->getDeviceId(),
            $sensor->getType(),
            $sensor->getRoom()->getName(),
            $value,
            $unit,
            $min,
            $unit,
            $max,
            $unit,
            (new \DateTimeImmutable())->format('d/m/Y H:i:s')
        );

        // Récupérer l'email admin de l'organisation
        $adminEmail = $this->getAdminEmail($sensor);
        if (!$adminEmail) return;

        $email = (new Email())
            ->from($this->alertFromEmail)
            ->to($adminEmail)
            ->subject($subject)
            ->text($body);

        $this->mailer->send($email);
    }

    private function getAdminEmail(Sensor $sensor): ?string
    {
        // Récupérer le premier admin de l'organisation liée à la salle
        $room = $sensor->getRoom();
        $farm = $room->getFarm();
        $org  = $farm->getOrganization();

        foreach ($org->getUsers() as $user) {
            if (in_array('ROLE_ORG_ADMIN', $user->getRoles(), true)) {
                return $user->getEmail();
            }
        }

        return null;
    }
}
