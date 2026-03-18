<?php

namespace App\DataFixtures;

use App\Entity\Farm;
use App\Entity\Organization;
use App\Entity\Plant;
use App\Entity\Room;
use App\Entity\Strain;
use App\Entity\User;
use App\Enum\LicenseStatus;
use App\Enum\PlantStage;
use App\Enum\PlantStatus;
use App\Enum\SubscriptionPlan;
use App\Repository\PlantEventRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly PlantEventRepository $plantEventRepository,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        /** @var User|null $demoUser */
        $demoUser = $manager->getRepository(User::class)->findOneBy(['email' => 'demo@cultivatrace.local']);

        if (!$demoUser instanceof User) {
            $demoUser = (new User())
                ->setEmail('demo@cultivatrace.local')
                ->setRole('ROLE_ADMIN')
                ->setRoles(['ROLE_ADMIN']);
            $demoUser->setPassword($this->passwordHasher->hashPassword($demoUser, 'demo123'));
            $manager->persist($demoUser);
            $manager->flush();
        }

        $organization = $demoUser->getOrganization();
        if (!$organization instanceof Organization) {
            $organization = new Organization();
            $manager->persist($organization);
        }

        $organization
            ->setName('Ferme Test')
            ->setCountry('FR')
            ->setPlan(SubscriptionPlan::PRO)
            ->setLicenseStatus(LicenseStatus::ACTIVE);
        $demoUser->setOrganization($organization);

        $manager->persist($organization);
        $manager->persist($demoUser);
        $manager->flush();

        $tenantId = $organization->getId();

        /** @var Farm|null $farm */
        $farm = $manager->getRepository(Farm::class)->findOneBy(['name' => 'Ferme Test']);
        if (!$farm instanceof Farm) {
            $farm = (new Farm())
                ->setName('Ferme Test')
                ->setAddress('42 route des serres, 33000 Bordeaux')
                ->setSurfaceM2(1200.0)
                ->setOrganization($organization)
                ->setTenantId($tenantId);
            $manager->persist($farm);
        } else {
            $farm
                ->setOrganization($organization)
                ->setTenantId($tenantId);
        }

        $rooms = [
            'Salle Veg' => $this->upsertRoom($manager, 'Salle Veg', 'veg', $farm, $tenantId),
            'Salle Flower' => $this->upsertRoom($manager, 'Salle Flower', 'flower', $farm, $tenantId),
        ];

        $strains = [
            'OG Kush' => $this->upsertStrain($manager, 'OG Kush', 'indica', $tenantId),
            'Amnesia' => $this->upsertStrain($manager, 'Amnesia', 'sativa', $tenantId),
            'Gelato' => $this->upsertStrain($manager, 'Gelato', 'hybrid', $tenantId),
        ];

        $manager->flush();

        $definitions = [
            ['rfid' => 'DEV-PLANT-001', 'room' => 'Salle Veg', 'strain' => 'OG Kush', 'stage' => PlantStage::GERMINATION, 'days' => 3],
            ['rfid' => 'DEV-PLANT-002', 'room' => 'Salle Veg', 'strain' => 'Amnesia', 'stage' => PlantStage::GERMINATION, 'days' => 4],
            ['rfid' => 'DEV-PLANT-003', 'room' => 'Salle Veg', 'strain' => 'Gelato', 'stage' => PlantStage::GERMINATION, 'days' => 5],
            ['rfid' => 'DEV-PLANT-004', 'room' => 'Salle Veg', 'strain' => 'OG Kush', 'stage' => PlantStage::VEGETATION, 'days' => 11],
            ['rfid' => 'DEV-PLANT-005', 'room' => 'Salle Veg', 'strain' => 'Amnesia', 'stage' => PlantStage::VEGETATION, 'days' => 13],
            ['rfid' => 'DEV-PLANT-006', 'room' => 'Salle Veg', 'strain' => 'Gelato', 'stage' => PlantStage::VEGETATION, 'days' => 16],
            ['rfid' => 'DEV-PLANT-007', 'room' => 'Salle Flower', 'strain' => 'OG Kush', 'stage' => PlantStage::FLOWERING, 'days' => 31],
            ['rfid' => 'DEV-PLANT-008', 'room' => 'Salle Flower', 'strain' => 'Amnesia', 'stage' => PlantStage::FLOWERING, 'days' => 33],
            ['rfid' => 'DEV-PLANT-009', 'room' => 'Salle Flower', 'strain' => 'Gelato', 'stage' => PlantStage::FLOWERING, 'days' => 36],
            ['rfid' => 'DEV-PLANT-010', 'room' => 'Salle Flower', 'strain' => 'OG Kush', 'stage' => PlantStage::VEGETATION, 'days' => 20],
        ];

        foreach ($definitions as $definition) {
            /** @var Plant|null $existingPlant */
            $existingPlant = $manager->getRepository(Plant::class)->findOneBy(['rfidTag' => $definition['rfid']]);
            $germinatedAt = new \DateTimeImmutable(sprintf('-%d days', $definition['days']));

            if (!$existingPlant instanceof Plant) {
                $existingPlant = (new Plant())
                    ->setTenantId($tenantId)
                    ->setRoom($rooms[$definition['room']])
                    ->setStrain($strains[$definition['strain']])
                    ->setRfidTag($definition['rfid'])
                    ->setStage($definition['stage'])
                    ->setStatus(PlantStatus::ACTIVE)
                    ->setGerminatedAt($germinatedAt)
                    ->setCreatedBy($demoUser);

                $manager->persist($existingPlant);
                $manager->flush();
            }

            $this->ensureMinimumEvents($existingPlant, $demoUser, $definition['stage'], $germinatedAt);
        }
    }

    private function ensureMinimumEvents(
        Plant $plant,
        User $demoUser,
        PlantStage $stage,
        \DateTimeImmutable $germinatedAt,
    ): void {
        $events = $this->plantEventRepository->findByPlantOrderedAsc($plant->getId());

        if (count($events) === 0) {
            $this->plantEventRepository->appendEvent(
                $plant,
                'germination',
                $demoUser,
                ['seeded_at' => $germinatedAt->format(DATE_ATOM)],
                sprintf('Mise en culture %s', $plant->getRfidTag()),
            );
            $events = $this->plantEventRepository->findByPlantOrderedAsc($plant->getId());
        }

        $eventTypes = array_map(static fn($event) => $event->getEventType(), $events);

        if (($stage === PlantStage::VEGETATION || $stage === PlantStage::FLOWERING) && !in_array('stage_change', $eventTypes, true)) {
            $this->plantEventRepository->appendEvent(
                $plant,
                'stage_change',
                $demoUser,
                ['from' => 'germination', 'to' => 'vegetation'],
                'Passage en vegetation',
            );
            $eventTypes[] = 'stage_change';
        }

        if ($stage === PlantStage::FLOWERING) {
            $hasFloweringTransition = false;
            foreach ($events as $event) {
                $payload = $event->getPayload() ?? [];
                if ($event->getEventType() === 'stage_change' && ($payload['to'] ?? null) === 'flowering') {
                    $hasFloweringTransition = true;
                    break;
                }
            }

            if (!$hasFloweringTransition) {
                $this->plantEventRepository->appendEvent(
                    $plant,
                    'stage_change',
                    $demoUser,
                    ['from' => 'vegetation', 'to' => 'flowering'],
                    'Passage en floraison',
                );
            }
        }

        $events = $this->plantEventRepository->findByPlantOrderedAsc($plant->getId());
        while (count($events) < 3) {
            $this->plantEventRepository->appendEvent(
                $plant,
                'note',
                $demoUser,
                ['source' => 'fixtures'],
                sprintf('Observation fixture #%d', count($events)),
            );
            $events = $this->plantEventRepository->findByPlantOrderedAsc($plant->getId());
        }
    }

    private function upsertRoom(ObjectManager $manager, string $name, string $type, Farm $farm, mixed $tenantId): Room
    {
        /** @var Room|null $room */
        $room = $manager->getRepository(Room::class)->findOneBy(['name' => $name]);
        if (!$room instanceof Room) {
            $room = new Room();
            $manager->persist($room);
        }

        return $room
            ->setName($name)
            ->setDescription(sprintf('%s development room', $name))
            ->setType($type)
            ->setCapacityMax(50)
            ->setFarm($farm)
            ->setTenantId($tenantId);
    }

    private function upsertStrain(ObjectManager $manager, string $name, string $genetics, mixed $tenantId): Strain
    {
        /** @var Strain|null $strain */
        $strain = $manager->getRepository(Strain::class)->findOneBy(['name' => $name]);
        if (!$strain instanceof Strain) {
            $strain = new Strain();
            $manager->persist($strain);
        }

        return $strain
            ->setName($name)
            ->setGenetics($genetics)
            ->setCannabisType('marijuana')
            ->setFloweringDays(match ($name) {
                'OG Kush' => 60,
                'Amnesia' => 70,
                default => 63,
            })
            ->setNotes(sprintf('%s fixture dev strain', $name))
            ->setTenantId($tenantId);
    }
}
