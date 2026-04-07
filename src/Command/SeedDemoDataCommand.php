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
use App\Entity\Organization;
use App\Entity\User;
use App\Enum\LicenseStatus;
use App\Enum\SubscriptionPlan;
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
            ->setLicenseStatus(LicenseStatus::PENDING);

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
}
