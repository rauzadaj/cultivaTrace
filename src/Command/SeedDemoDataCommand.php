<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\Cultivation\Enum\CropStage;
use App\Domain\Cultivation\Enum\JournalEntryType;
use App\Domain\Cultivation\Model\Crop;
use App\Domain\Cultivation\Model\Genetic;
use App\Domain\Cultivation\Model\JournalEntry;
use App\Domain\Cultivation\ValueObject\NutrientConcentration;
use App\Domain\Cultivation\ValueObject\PhLevel;
use App\Domain\Operations\Model\OperationalService;
use App\Entity\Farm;
use App\Entity\Organization;
use App\Entity\Plant;
use App\Entity\Room;
use App\Entity\Sensor;
use App\Entity\Strain;
use App\Entity\User;
use App\Enum\LicenseStatus;
use App\Enum\PlantStage;
use App\Enum\PlantStatus;
use App\Enum\RoomType;
use App\Enum\SubscriptionPlan;
use App\Enum\UserAccountStatus;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:seed-demo-data',
    description: 'Seed idempotent local demo data for CultivaTrace.',
)]
final class SeedDemoDataCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly KernelInterface $kernel,
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!in_array($this->kernel->getEnvironment(), ['dev', 'test'], true)) {
            $io->error('app:seed-demo-data is restricted to dev and test environments.');

            return Command::FAILURE;
        }

        $demoUser = $this->upsertDemoUser();
        $organization = $demoUser->getOrganization();
        if (!$organization instanceof Organization) {
            throw new \LogicException('Demo user must belong to an organization before seeding crops.');
        }

        $alpineResin = $this->upsertGenetic(
            code: 'CT-ALP',
            name: 'Alpine Resin',
            vendor: 'Cultiva Labs',
            metadata: ['lineage' => 'Hybrid', 'thc' => '22%'],
        );
        $solarKush = $this->upsertGenetic(
            code: 'CT-SOL',
            name: 'Solar Kush',
            vendor: 'Cultiva Labs',
            metadata: ['lineage' => 'Indica', 'thc' => '24%'],
        );
        $this->upsertOperationalService('Genetics', 'Cultivation', 'Top performing genetic lines visible in realtime.', 'mdi-leaf', 'primary', 'CT-ALP', 10);
        $this->upsertOperationalService('Chemistry', 'Environment', 'Average pH and nutrient concentration controls.', 'mdi-flask-outline', 'warning', '6.14 pH', 20);
        $this->upsertOperationalService('Journal', 'Compliance', 'Append-only field event log with immutable history.', 'mdi-book-lock-outline', 'success', '5 events', 30);

        $this->upsertCrop(
            organization: $organization,
            batchCode: 'ALP-2401-A',
            displayName: 'Alpine Resin Lot A',
            genetic: $alpineResin,
            seededAt: $this->inParisTimezone('2026-02-20 08:00:00'),
            currentStage: CropStage::Veg,
            journalBlueprints: [
                [
                    'type' => JournalEntryType::Irrigation,
                    'occurredAt' => '2026-03-10 07:00:00',
                    'notes' => 'Irrigation debut de jour.',
                    'metadata' => ['source' => 'demo', 'operator' => 'Field Operator'],
                    'ph' => 6.10,
                    'ppm' => 840,
                ],
                [
                    'type' => JournalEntryType::EnvironmentCheck,
                    'occurredAt' => '2026-03-10 12:30:00',
                    'notes' => 'Controle climat serre nord.',
                    'metadata' => ['source' => 'demo', 'temperatureC' => 24.4, 'humidity' => 58],
                    'ph' => 6.02,
                    'ppm' => 820,
                ],
            ],
        );

        $this->upsertCrop(
            organization: $organization,
            batchCode: 'ALP-2309-H',
            displayName: 'Alpine Resin Harvest',
            genetic: $alpineResin,
            seededAt: $this->inParisTimezone('2025-11-10 09:00:00'),
            currentStage: CropStage::Harvest,
            harvestedAt: $this->inParisTimezone('2026-01-12 10:00:00'),
            finalYieldGrams: 512,
            journalBlueprints: [
                [
                    'type' => JournalEntryType::StageTransition,
                    'occurredAt' => '2026-01-12 10:00:00',
                    'notes' => 'Transition workflow de flower vers harvest.',
                    'metadata' => ['source' => 'workflow', 'from' => 'flower', 'to' => 'harvest'],
                    'ph' => 6.40,
                    'ppm' => 980,
                ],
            ],
        );

        $this->upsertCrop(
            organization: $organization,
            batchCode: 'SOL-2402-F',
            displayName: 'Solar Kush Flower',
            genetic: $solarKush,
            seededAt: $this->inParisTimezone('2026-02-05 07:30:00'),
            currentStage: CropStage::Flower,
            journalBlueprints: [
                [
                    'type' => JournalEntryType::Fertilization,
                    'occurredAt' => '2026-03-10 09:15:00',
                    'notes' => 'Apport floraison haute disponibilite.',
                    'metadata' => ['source' => 'demo', 'operator' => 'Field Operator'],
                    'ph' => 6.28,
                    'ppm' => 1120,
                ],
                [
                    'type' => JournalEntryType::Observation,
                    'occurredAt' => '2026-03-10 16:20:00',
                    'notes' => 'Canopy stable, coloration reguliere.',
                    'metadata' => ['source' => 'demo', 'operator' => 'Field Operator'],
                    'ph' => 6.15,
                    'ppm' => 1090,
                ],
            ],
        );

        $this->entityManager->flush();

        // ── IoT / cultivation layer ──────────────────────────────────────────
        $io->section('IoT layer: Farm / Rooms / Sensors / Plants');
        $farm    = $this->upsertFarm($organization);
        $vegRoom = $this->upsertRoom($farm, 'Serre Végétation A', RoomType::Veg, 50);
        $flwRoom = $this->upsertRoom($farm, 'Serre Floraison B', RoomType::Flower, 40);
        $clnRoom = $this->upsertRoom($farm, 'Clone / Nursery', RoomType::Clone, 100);
        $this->entityManager->flush();

        $strainHybrid  = $this->upsertStrain($organization, 'Blue Dream', 'hybrid');
        $strainIndica1 = $this->upsertStrain($organization, 'OG Kush', 'indica');
        $strainIndica2 = $this->upsertStrain($organization, 'Northern Lights', 'indica');
        $this->entityManager->flush();

        // Plants
        $this->upsertPlant($organization, $vegRoom, $strainHybrid, $demoUser, PlantStage::VEGETATION, '-45 days', 'RFID-VEG-001');
        $this->upsertPlant($organization, $vegRoom, $strainHybrid, $demoUser, PlantStage::VEGETATION, '-40 days', 'RFID-VEG-002');
        $this->upsertPlant($organization, $vegRoom, $strainIndica1, $demoUser, PlantStage::VEGETATION, '-38 days', 'RFID-VEG-003');
        $this->upsertPlant($organization, $vegRoom, $strainIndica1, $demoUser, PlantStage::VEGETATION, '-35 days', 'RFID-VEG-004');
        $this->upsertPlant($organization, $vegRoom, $strainHybrid, $demoUser, PlantStage::VEGETATION, '-30 days', 'RFID-VEG-005');

        $this->upsertPlant($organization, $flwRoom, $strainIndica2, $demoUser, PlantStage::FLOWERING, '-65 days', 'RFID-FLW-001');
        $this->upsertPlant($organization, $flwRoom, $strainIndica2, $demoUser, PlantStage::FLOWERING, '-62 days', 'RFID-FLW-002');
        $this->upsertPlant($organization, $flwRoom, $strainHybrid, $demoUser, PlantStage::FLOWERING, '-60 days', 'RFID-FLW-003');
        $this->upsertPlant($organization, $flwRoom, $strainIndica1, $demoUser, PlantStage::FLOWERING, '-58 days', 'RFID-FLW-004');
        $this->upsertPlant($organization, $flwRoom, $strainIndica2, $demoUser, PlantStage::FLOWERING, '-55 days', 'RFID-FLW-005');
        $this->upsertPlant($organization, $flwRoom, $strainHybrid, $demoUser, PlantStage::FLOWERING, '-50 days', 'RFID-FLW-006');

        $this->upsertPlant($organization, $clnRoom, $strainHybrid, $demoUser, PlantStage::GERMINATION, '-8 days', 'RFID-CLN-001');
        $this->upsertPlant($organization, $clnRoom, $strainIndica1, $demoUser, PlantStage::GERMINATION, '-7 days', 'RFID-CLN-002');
        $this->upsertPlant($organization, $clnRoom, $strainIndica2, $demoUser, PlantStage::GERMINATION, '-6 days', 'RFID-CLN-003');
        $this->upsertPlant($organization, $flwRoom, $strainIndica2, $demoUser, PlantStage::HARVEST, '-90 days', 'RFID-HRV-001', PlantStatus::HARVESTED);
        $this->entityManager->flush();

        // Sensors + historical readings
        $sensors = [
            ['room' => $vegRoom, 'type' => 'temperature', 'deviceId' => 'TEMP-VEG-A', 'base' => 24.0, 'amp' => 2.0, 'unit' => '°C'],
            ['room' => $vegRoom, 'type' => 'humidity',    'deviceId' => 'HUM-VEG-A',  'base' => 60.0, 'amp' => 5.0, 'unit' => '%'],
            ['room' => $flwRoom, 'type' => 'temperature', 'deviceId' => 'TEMP-FLW-B', 'base' => 22.0, 'amp' => 1.5, 'unit' => '°C'],
            ['room' => $flwRoom, 'type' => 'humidity',    'deviceId' => 'HUM-FLW-B',  'base' => 50.0, 'amp' => 4.0, 'unit' => '%'],
            ['room' => $clnRoom, 'type' => 'temperature', 'deviceId' => 'TEMP-CLN-C', 'base' => 26.0, 'amp' => 1.0, 'unit' => '°C'],
            ['room' => $clnRoom, 'type' => 'humidity',    'deviceId' => 'HUM-CLN-C',  'base' => 68.0, 'amp' => 4.0, 'unit' => '%'],
        ];

        foreach ($sensors as $def) {
            /** @var Room $room */
            $room   = $def['room'];
            $sensor = $this->upsertSensor($organization, $room, $def['type'], $def['deviceId']);
            $this->entityManager->flush();
            $this->seedSensorReadings($sensor, $organization, (float) $def['base'], (float) $def['amp']);
            $io->text(sprintf('  ✓ %s (%s) — readings seeded', $def['deviceId'], $def['type']));
        }

        $io->success(sprintf(
            'Demo environment ready. Use %s / demo123 to authenticate, then POST to /api/login.',
            $demoUser->getEmail(),
        ));

        return Command::SUCCESS;
    }

    private function upsertOperationalService(
        string $name,
        string $category,
        string $description,
        string $icon,
        string $tone,
        string $statusLabel,
        int $position,
    ): void {
        /** @var OperationalService|null $service */
        $service = $this->entityManager->getRepository(OperationalService::class)->findOneBy(['name' => $name]);

        if (!$service instanceof OperationalService) {
            $service = new OperationalService();
            $this->entityManager->persist($service);
        }

        $service
            ->setName($name)
            ->setCategory($category)
            ->setDescription($description)
            ->setIcon($icon)
            ->setTone($tone)
            ->setStatusLabel($statusLabel)
            ->setPosition($position);
    }

    private function upsertDemoUser(): User
    {
        $organization = $this->upsertDemoOrganization();

        /** @var User|null $user */
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'demo@cultivatrace.local']);

        if (!$user instanceof User) {
            $user = (new User())
                ->setEmail('demo@cultivatrace.local');

            $this->entityManager->persist($user);
        }

        $user
            ->setRoles(['ROLE_ORG_ADMIN'])
            ->setOrganization($organization)
            ->setAccountStatus(UserAccountStatus::ACTIVE)
            ->setEmailVerifiedAt(new \DateTimeImmutable())
            ->setEmailVerificationTokenHash(null)
            ->setEmailVerificationExpiresAt(null)
            ->setPassword($this->passwordHasher->hashPassword($user, 'demo123'));

        return $user;
    }

    private function upsertDemoOrganization(): Organization
    {
        /** @var Organization|null $organization */
        $organization = $this->entityManager->getRepository(Organization::class)->findOneBy(['name' => 'CultivaTrace Demo']);

        if (!$organization instanceof Organization) {
            $organization = (new Organization())
                ->setName('CultivaTrace Demo');

            $this->entityManager->persist($organization);
        }

        $organization
            ->setCountry('FR')
            ->setPlan(SubscriptionPlan::PRO)
            ->setLicenseStatus(LicenseStatus::ACTIVE)
            ->setLicenseExpiresAt(new \DateTimeImmutable('+1 year'));

        return $organization;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function upsertGenetic(string $code, string $name, ?string $vendor, array $metadata): Genetic
    {
        /** @var Genetic|null $genetic */
        $genetic = $this->entityManager->getRepository(Genetic::class)->findOneBy(['code' => $code]);

        if (!$genetic instanceof Genetic) {
            $genetic = new Genetic();
            $genetic
                ->setCode($code)
                ->setName($name)
                ->setVendor($vendor)
                ->setMetadata($metadata);

            $this->entityManager->persist($genetic);
        }

        return $genetic;
    }

    /**
     * @param array<int, array{type: JournalEntryType, occurredAt: string, notes: string, metadata: array<string, mixed>, ph: float, ppm: int}> $journalBlueprints
     */
    private function upsertCrop(
        Organization $organization,
        string $batchCode,
        string $displayName,
        Genetic $genetic,
        \DateTimeImmutable $seededAt,
        CropStage $currentStage,
        array $journalBlueprints,
        ?\DateTimeImmutable $harvestedAt = null,
        ?int $finalYieldGrams = null,
    ): void {
        /** @var Crop|null $crop */
        $crop = $this->entityManager->getRepository(Crop::class)->findOneBy(['batchCode' => $batchCode]);

        if (!$crop instanceof Crop) {
            $crop = new Crop();
            $crop
                ->setTenantId($organization->getId())
                ->setBatchCode($batchCode)
                ->setDisplayName($displayName)
                ->setGenetic($genetic)
                ->setSeededAt($seededAt);

            if ($currentStage === CropStage::Harvest) {
                $crop->markHarvested($finalYieldGrams ?? 0, $harvestedAt);
            } else {
                $crop->setCurrentStageValue($currentStage->value);
            }

            $this->entityManager->persist($crop);
        } else {
            return;
        }

        foreach ($journalBlueprints as $journalBlueprint) {
            $crop->addJournalEntry(
                (new JournalEntry())
                    ->setType($journalBlueprint['type'])
                    ->setOccurredAt($this->inParisTimezone($journalBlueprint['occurredAt']))
                    ->setNotes($journalBlueprint['notes'])
                    ->setMetadata($journalBlueprint['metadata'])
                    ->setPhLevel(new PhLevel($journalBlueprint['ph']))
                    ->setNutrientConcentration(new NutrientConcentration($journalBlueprint['ppm'])),
            );
        }
    }

    private function inParisTimezone(string $dateTime): \DateTimeImmutable
    {
        return new \DateTimeImmutable($dateTime, new \DateTimeZone('Europe/Paris'));
    }

    private function upsertFarm(Organization $organization): Farm
    {
        /** @var Farm|null $farm */
        $farm = $this->entityManager->getRepository(Farm::class)->findOneBy(['name' => 'CultivaTrace Demo Farm']);

        if (!$farm instanceof Farm) {
            $farm = (new Farm())
                ->setName('CultivaTrace Demo Farm')
                ->setOrganization($organization)
                ->setTenantId($organization->getId());
            $this->entityManager->persist($farm);
        }

        return $farm;
    }

    private function upsertRoom(Farm $farm, string $name, RoomType $type, int $capacity): Room
    {
        /** @var Room|null $room */
        $room = $this->entityManager->getRepository(Room::class)->findOneBy(['name' => $name]);

        if (!$room instanceof Room) {
            $room = (new Room())
                ->setFarm($farm)
                ->setTenantId($farm->getTenantId())
                ->setName($name)
                ->setType($type)
                ->setCapacityMax($capacity);
            $this->entityManager->persist($room);
        }

        return $room;
    }

    private function upsertStrain(Organization $organization, string $name, string $genetics): Strain
    {
        /** @var Strain|null $strain */
        $strain = $this->entityManager->getRepository(Strain::class)->findOneBy(['name' => $name, 'tenantId' => $organization->getId()]);

        if (!$strain instanceof Strain) {
            $strain = (new Strain())
                ->setTenantId($organization->getId())
                ->setName($name)
                ->setGenetics($genetics)
                ->setCannabisType('marijuana');
            $this->entityManager->persist($strain);
        }

        return $strain;
    }

    private function upsertSensor(Organization $organization, Room $room, string $type, string $deviceId): Sensor
    {
        /** @var Sensor|null $sensor */
        $sensor = $this->entityManager->getRepository(Sensor::class)->findOneBy(['deviceId' => $deviceId]);

        if (!$sensor instanceof Sensor) {
            $sensor = (new Sensor())
                ->setTenantId($organization->getId())
                ->setRoom($room)
                ->setType($type)
                ->setDeviceId($deviceId)
                ->setProtocol('mqtt')
                ->setStatus('active')
                ->setLastSeen(new \DateTimeImmutable());
            $this->entityManager->persist($sensor);
        }

        return $sensor;
    }

    private function upsertPlant(
        Organization $organization,
        Room $room,
        Strain $strain,
        User $createdBy,
        PlantStage $stage,
        string $germinatedAgo,
        string $rfidTag,
        PlantStatus $status = PlantStatus::ACTIVE,
    ): void {
        /** @var Plant|null $plant */
        $plant = $this->entityManager->getRepository(Plant::class)->findOneBy(['rfidTag' => $rfidTag]);

        if (!$plant instanceof Plant) {
            $plant = (new Plant())
                ->setTenantId($organization->getId())
                ->setRoom($room)
                ->setStrain($strain)
                ->setCreatedBy($createdBy)
                ->setGerminatedAt(new \DateTimeImmutable($germinatedAgo))
                ->setStage($stage)
                ->setStatus($status)
                ->setRfidTag($rfidTag);
            $this->entityManager->persist($plant);
        }
    }

    private function seedSensorReadings(Sensor $sensor, Organization $organization, float $base, float $amplitude): void
    {
        $sensorId = (string) $sensor->getId();
        $tenantId = (string) $organization->getId();

        // Wipe existing demo readings for idempotency
        $this->connection->executeStatement(
            'DELETE FROM sensor_reading WHERE sensor_id = :sid AND tenant_id = :tid',
            ['sid' => $sensorId, 'tid' => $tenantId]
        );

        // One reading every 3 hours for the last 30 days = 240 points
        $rows   = [];
        $params = [];
        $now    = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        for ($i = 239; $i >= 0; $i--) {
            $ts    = $now->modify(sprintf('-%d hours', $i * 3));
            $noise = (mt_rand(-100, 100) / 100) * ($amplitude * 0.4);
            $value = round($base + sin($i / 8.0) * $amplitude * 0.5 + $noise, 2);
            $key   = "r{$i}";

            $rows[]           = "(:sid{$key}, :tid{$key}, :val{$key}, :rat{$key})";
            $params["sid{$key}"] = $sensorId;
            $params["tid{$key}"] = $tenantId;
            $params["val{$key}"] = $value;
            $params["rat{$key}"] = $ts->format('Y-m-d H:i:sP');
        }

        // Batch insert in chunks of 50 to stay within parameter limits
        foreach (array_chunk($rows, 50) as $chunkIndex => $chunk) {
            $chunkParams = [];
            foreach ($chunk as $row) {
                preg_match_all('/:([\w]+)/', $row, $matches);
                foreach ($matches[1] as $paramKey) {
                    $chunkParams[$paramKey] = $params[$paramKey];
                }
            }
            $this->connection->executeStatement(
                'INSERT INTO sensor_reading (sensor_id, tenant_id, value, recorded_at) VALUES ' . implode(',', $chunk),
                $chunkParams
            );
        }
    }
}
