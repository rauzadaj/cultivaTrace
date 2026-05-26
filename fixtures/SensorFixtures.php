<?php

namespace App\DataFixtures;

use App\Entity\Sensor;
use App\Repository\SensorReadingRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * SensorFixtures — creates simulated sensors and historical readings.
 *
 * Depends on AppFixtures (rooms must exist).
 * Generates 90 days of simulated historical data.
 */
class SensorFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        private readonly SensorReadingRepository $readingRepo,
    ) {}

    public function load(ObjectManager $manager): void
    {
        $rooms = $manager->getRepository(\App\Entity\Room::class)->findAll();

        if (empty($rooms)) {
            echo "No room found — run AppFixtures first.\n";
            return;
        }

        $sensorConfigs = [
            ['type' => 'temperature', 'thresholds' => ['min' => 18.0, 'max' => 28.0, 'unit' => '°C', 'alertCooldownMinutes' => 60]],
            ['type' => 'humidity',    'thresholds' => ['min' => 40.0, 'max' => 70.0, 'unit' => '%',  'alertCooldownMinutes' => 60]],
            ['type' => 'co2',         'thresholds' => ['min' => 400,  'max' => 1500,  'unit' => 'ppm','alertCooldownMinutes' => 30]],
        ];

        foreach ($rooms as $room) {
            foreach ($sensorConfigs as $config) {
                $sensor = new Sensor();
                $sensor->setTenantId($room->getTenantId());
                $sensor->setRoom($room);
                $sensor->setType($config['type']);
                $sensor->setDeviceId(sprintf('SIM-%s-%s', strtoupper($config['type']), substr((string) $room->getId(), 0, 4)));
                $sensor->setProtocol('simulated');
                $sensor->setStatus('online');
                $sensor->setLastSeen(new \DateTimeImmutable());
                $sensor->setThresholds($config['thresholds']);

                $manager->persist($sensor);
                $manager->flush();

                // Generate 90 days of historical data (1 reading/hour)
                $this->generateHistoricalData($sensor, $config['type']);
            }
        }

        echo "Sensor fixtures loaded with 90d of historical data.\n";
    }

    private function generateHistoricalData(Sensor $sensor, string $type): void
    {
        $hoursToGenerate = 90 * 24; // 90 days
        $now             = new \DateTimeImmutable();

        // Realistic base values per type
        [$base, $variance] = match ($type) {
            'temperature' => [23.0, 3.0],
            'humidity'    => [55.0, 10.0],
            'co2'         => [800.0, 200.0],
            'ph'          => [6.2, 0.4],
            'ec'          => [1.8, 0.3],
            default       => [50.0, 5.0],
        };

        // Insert in batches every 24h to avoid timeouts
        for ($h = $hoursToGenerate; $h >= 0; $h--) {
            $time  = $now->modify("-{$h} hours");
            // Sinusoidal variation to simulate day/night cycles
            $cycle = sin($h / 12 * M_PI) * ($variance * 0.3);
            $noise = (mt_rand(-100, 100) / 100) * $variance * 0.7;
            $value = round($base + $cycle + $noise, 2);

            // Clamp to realistic values
            $value = match ($type) {
                'temperature' => max(15.0, min(35.0, $value)),
                'humidity'    => max(20.0, min(90.0, $value)),
                'co2'         => max(300.0, min(2000.0, $value)),
                'ph'          => max(4.0, min(8.0, $value)),
                'ec'          => max(0.5, min(3.5, $value)),
                default       => max(0.0, $value),
            };

            $this->readingRepo->insert(
                (string) $sensor->getId(),
                (string) $sensor->getTenantId(),
                $value
            );
        }
    }

    public function getDependencies(): array
    {
        return [AppFixtures::class];
    }
}
