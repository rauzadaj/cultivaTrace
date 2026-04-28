<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Alert;
use App\Entity\Sensor;
use Doctrine\ORM\EntityManagerInterface;
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
        private readonly EntityManagerInterface $entityManager,
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

        $cacheMiss = false;
        $this->cache->get($cacheKey, function (ItemInterface $item) use ($cooldown, &$cacheMiss) {
            $item->expiresAfter($cooldown * 60);
            $cacheMiss = true;
            return true;
        });

        if (!$cacheMiss) {
            return false;
        }

        $this->persistAlert($sensor, $value);
        $this->sendAlert($sensor, $value);
        return true;
    }

    private function persistAlert(Sensor $sensor, float $value): void
    {
        $thresholds = $sensor->getThresholds() ?? [];
        $unit = (string) ($thresholds['unit'] ?? '');
        $min = $thresholds['min'] ?? '—';
        $max = $thresholds['max'] ?? '—';

        $alert = new Alert();
        $alert
            ->setTenantId($sensor->getTenantId())
            ->setSensor($sensor)
            ->setType('sensor_threshold')
            ->setSeverity($this->determineSeverity($thresholds, $value))
            ->setTitle(sprintf('%s · %s', $sensor->getRoom()->getName(), $sensor->getType()))
            ->setMessage(sprintf('%.2f%s hors plage autorisee (%s - %s%s)', $value, $unit, (string) $min, (string) $max, $unit))
            ->setContext(sprintf('Capteur %s', $sensor->getDeviceId()))
            ->setMetadata([
                'value' => $value,
                'unit' => $unit,
                'min' => $thresholds['min'] ?? null,
                'max' => $thresholds['max'] ?? null,
                'roomId' => (string) $sensor->getRoom()->getId(),
                'sensorId' => (string) $sensor->getId(),
                'sensorType' => $sensor->getType(),
            ]);

        $this->entityManager->persist($alert);
        $this->entityManager->flush();
    }

    private function determineSeverity(array $thresholds, float $value): string
    {
        $min = isset($thresholds['min']) ? (float) $thresholds['min'] : null;
        $max = isset($thresholds['max']) ? (float) $thresholds['max'] : null;

        if ($min !== null && $value < ($min * 0.9)) {
            return 'critical';
        }

        if ($max !== null && $value > ($max * 1.1)) {
            return 'critical';
        }

        return 'warning';
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
