<?php

namespace App\Controller;

use App\Entity\Sensor;
use App\Repository\SensorReadingRepository;
use App\Service\AlertService;
use App\Service\VpdService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * POST /api/sensors/{id}/reading
 *
 * Reçoit une lecture capteur (depuis le script de simulation MQTT
 * ou un capteur réel), la stocke dans sensor_reading,
 * publie vers Mercure pour le dashboard temps réel,
 * et vérifie les seuils pour les alertes.
 */
#[Route('/api/sensors/{id}/reading', methods: ['POST'])]
class SensorReadingController extends AbstractController
{
    public function __construct(
        private readonly SensorReadingRepository $readings,
        private readonly AlertService $alerts,
        private readonly VpdService $vpd,
        private readonly HubInterface $hub,
        private readonly EntityManagerInterface $em,
    ) {}

    public function __invoke(
        Sensor  $sensor,
        Request $request,
        #[CurrentUser] $user,
    ): JsonResponse {
        $data  = json_decode($request->getContent(), true) ?? [];
        $value = $data['value'] ?? null;

        if ($value === null || !is_numeric($value)) {
            return $this->json(['error' => 'value est obligatoire et doit être numérique'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $value = (float) $value;

        // 1. Stocker la lecture
        $this->readings->insert(
            (string) $sensor->getId(),
            (string) $sensor->getTenantId(),
            $value
        );

        // 2. Mettre à jour lastSeen + status du capteur
        $sensor->setLastSeen(new \DateTimeImmutable());
        $sensor->setStatus('online');
        $this->em->flush();

        // 3. Calculer le VPD si c'est un capteur température ou humidité
        $vpdData = null;
        if (in_array($sensor->getType(), ['temperature', 'humidity'], true)) {
            $vpdData = $this->computeVpdForRoom($sensor, $value);
        }

        // 4. Publier vers Mercure (SSE → frontend)
        $payload = [
            'sensorId'  => (string) $sensor->getId(),
            'roomId'    => (string) $sensor->getRoom()->getId(),
            'type'      => $sensor->getType(),
            'value'     => $value,
            'unit'      => $this->getUnit($sensor->getType()),
            'recordedAt'=> (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'vpd'       => $vpdData,
        ];

        $tenantId = (string) $sensor->getTenantId();
        $this->hub->publish(new Update(
            topics: [
                "cannas/{$tenantId}/sensors",
                "cannas/{$tenantId}/rooms/{$sensor->getRoom()->getId()}",
            ],
            data: json_encode($payload),
        ));

        // 5. Vérifier les seuils et alerter si nécessaire
        $alerted = $this->alerts->checkAndAlert($sensor, $value);

        return $this->json([
            'stored'  => true,
            'alerted' => $alerted,
            'vpd'     => $vpdData,
        ], Response::HTTP_CREATED);
    }

    /**
     * Tente de calculer le VPD si on a à la fois température et humidité
     * pour cette salle dans les dernières 5 minutes.
     */
    private function computeVpdForRoom(Sensor $sensor, float $currentValue): ?array
    {
        $roomId   = (string) $sensor->getRoom()->getId();
        $recent   = $this->readings->findRecentForRoom($roomId, 5);

        // Trouver la dernière valeur de l'autre type (temp ou humidity)
        $sensors  = $this->em->getRepository(Sensor::class)->findBy([
            'room' => $sensor->getRoom(),
        ]);

        $temp     = null;
        $humidity = null;

        if ($sensor->getType() === 'temperature') {
            $temp = $currentValue;
            // Chercher humidité récente
            foreach ($sensors as $s) {
                if ($s->getType() === 'humidity') {
                    $latest = $this->readings->findLatest((string) $s->getId());
                    if ($latest) $humidity = (float) $latest['value'];
                }
            }
        } else {
            $humidity = $currentValue;
            // Chercher température récente
            foreach ($sensors as $s) {
                if ($s->getType() === 'temperature') {
                    $latest = $this->readings->findLatest((string) $s->getId());
                    if ($latest) $temp = (float) $latest['value'];
                }
            }
        }

        if ($temp === null || $humidity === null) return null;

        return $this->vpd->computeAndEvaluate($temp, $humidity, 'vegetation');
    }

    private function getUnit(string $type): string
    {
        return match ($type) {
            'temperature' => '°C',
            'humidity'    => '%',
            'co2'         => 'ppm',
            'ph'          => 'pH',
            'ec'          => 'mS/cm',
            'vpd'         => 'kPa',
            default       => '',
        };
    }
}
