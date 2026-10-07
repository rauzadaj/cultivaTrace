<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Plant;
use App\Entity\Sensor;
use App\Entity\User;
use App\Enum\PlantStage;
use App\Enum\PlantStatus;
use App\Repository\SensorReadingRepository;
use App\Security\Voter\TenantAwareVoter;
use App\Service\AlertService;
use App\Service\License\LicenseGuard;
use App\Service\MercureService;
use App\Service\VpdService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * POST /api/sensors/{id}/reading
 *
 * Receives a sensor reading (from the MQTT simulation script
 * or a real sensor), stores it in sensor_reading,
 * publishes to Mercure for the real-time dashboard,
 * and checks thresholds for alerts.
 */
#[Route('/api/sensors/{id}/reading', methods: ['POST'])]
class SensorReadingController extends AbstractController
{
    public function __construct(
        private readonly SensorReadingRepository $readings,
        private readonly AlertService $alerts,
        private readonly VpdService $vpd,
        private readonly MercureService $mercure,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        private readonly LicenseGuard $licenseGuard,
    ) {
    }

    public function __invoke(
        Sensor  $sensor,
        Request $request,
        #[CurrentUser] ?User $user,
    ): JsonResponse {
        $this->assertWriteAccess($sensor, $user);

        $data  = json_decode($request->getContent(), true) ?? [];
        $value = $data['value'] ?? null;

        if ($value === null || !is_numeric($value)) {
            return $this->json(['error' => 'value is required and must be numeric'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $value = (float) $value;

        if (!$this->isValueInBounds($sensor->getType(), $value)) {
            return $this->json(
                ['error' => sprintf('Value %.4f is out of valid physical range for sensor type "%s".', $value, $sensor->getType())],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $this->readings->insert(
            (string) $sensor->getId(),
            (string) $sensor->getTenantId(),
            $value
        );

        $sensor->setLastSeen(new \DateTimeImmutable());
        $sensor->setStatus('online');
        $this->em->flush();

        $vpdData = null;
        if (in_array($sensor->getType(), ['temperature', 'humidity'], true)) {
            $vpdData = $this->computeVpdForRoom($sensor, $value);
        }

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

        try {
            $this->mercure->publishPrivateUpdate([
                $this->mercure->buildSensorTopic($tenantId, (string) $sensor->getId()),
                $this->mercure->buildRoomTopic($tenantId, (string) $sensor->getRoom()->getId()),
            ], $payload);
        } catch (\Throwable $exception) {
            $this->logger->warning('Mercure publish failed for sensor reading.', [
                'sensorId' => (string) $sensor->getId(),
                'tenantId' => $tenantId,
                'exception' => $exception,
            ]);
        }

        $alerted = $this->alerts->checkAndAlert($sensor, $value);

        return $this->json([
            'stored'  => true,
            'alerted' => $alerted,
            'vpd'     => $vpdData,
        ], Response::HTTP_CREATED);
    }

    private const VALUE_BOUNDS = [
        'temperature' => [-50.0,  80.0],
        'humidity'    => [0.0,   100.0],
        'co2'         => [0.0, 10000.0],
        'ph'          => [0.0,    14.0],
        'ec'          => [0.0,   100.0],
        'vpd'         => [0.0,    10.0],
    ];

    private function isValueInBounds(string $type, float $value): bool
    {
        $bounds = self::VALUE_BOUNDS[$type] ?? null;
        if ($bounds === null) {
            return true;
        }
        return $value >= $bounds[0] && $value <= $bounds[1];
    }

    private function assertWriteAccess(Sensor $sensor, ?User $user): void
    {
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('Authenticated user required.');
        }

        if (
            !$this->isGranted('ROLE_ORG_USER')
            && !$this->isGranted('ROLE_API')
        ) {
            throw new AccessDeniedHttpException('Insufficient role for sensor writes.');
        }

        if (!$this->isGranted(TenantAwareVoter::ACCESS, $sensor)) {
            throw new AccessDeniedHttpException('Cross-tenant sensor writes are forbidden.');
        }

        $this->licenseGuard->assertLicenseApproved($user->getOrganization());
    }

    /**
     * @return array<string, mixed>|null
     */
    private function computeVpdForRoom(Sensor $sensor, float $currentValue): ?array
    {
        $roomId   = (string) $sensor->getRoom()->getId();
        $tenantId = (string) $sensor->getTenantId();
        $recent   = $this->readings->findRecentForRoom($roomId, $tenantId, 5);

        $sensors  = $this->em->getRepository(Sensor::class)->findBy([
            'room' => $sensor->getRoom(),
        ]);

        $temp     = null;
        $humidity = null;

        if ($sensor->getType() === 'temperature') {
            $temp = $currentValue;
            foreach ($sensors as $s) {
                if ($s->getType() === 'humidity') {
                    $latest = $this->readings->findLatest((string) $s->getId(), $tenantId);
                    if ($latest) {
                        $humidity = (float) $latest['value'];
                    }
                }
            }
        } else {
            $humidity = $currentValue;
            foreach ($sensors as $s) {
                if ($s->getType() === 'temperature') {
                    $latest = $this->readings->findLatest((string) $s->getId(), $tenantId);
                    if ($latest) {
                        $temp = (float) $latest['value'];
                    }
                }
            }
        }

        if ($temp === null || $humidity === null) {
            return null;
        }

        $dominantStage = $this->dominantStageForRoom($sensor);

        return $this->vpd->computeAndEvaluate($temp, $humidity, $dominantStage->value);
    }

    private function dominantStageForRoom(Sensor $sensor): PlantStage
    {
        $result = $this->em->createQuery(
            'SELECT p.stage, COUNT(p.id) AS cnt
             FROM App\Entity\Plant p
             WHERE p.room = :room
               AND p.tenantId = :tenantId
               AND p.status = :status
             GROUP BY p.stage
             ORDER BY cnt DESC, p.stage ASC'
        )
        ->setParameter('room', $sensor->getRoom())
        ->setParameter('tenantId', $sensor->getTenantId(), 'uuid')
        ->setParameter('status', PlantStatus::ACTIVE)
        ->setMaxResults(1)
        ->getOneOrNullResult();

        if ($result === null) {
            return PlantStage::VEGETATION;
        }

        return $result['stage'] instanceof PlantStage
            ? $result['stage']
            : PlantStage::from($result['stage']);
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
