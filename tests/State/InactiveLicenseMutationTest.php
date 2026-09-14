<?php

declare(strict_types=1);

namespace App\Tests\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Organization;
use App\Entity\Plant;
use App\Entity\Room;
use App\Entity\Sensor;
use App\Entity\User;
use App\Enum\LicenseStatus;
use App\Repository\PlantEventRepository;
use App\Service\License\LicenseGuard;
use App\Service\PlanLimitsService;
use App\State\PlantStateProcessor;
use App\State\RoomStateProcessor;
use App\State\SensorStateProcessor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class InactiveLicenseMutationTest extends TestCase
{
    /** @return iterable<string, array{LicenseStatus, string}> */
    public static function mutations(): iterable
    {
        foreach ([LicenseStatus::PENDING, LicenseStatus::REJECTED, LicenseStatus::EXPIRED, LicenseStatus::SUSPENDED] as $status) {
            foreach (['plant', 'room', 'sensor', 'delete_sensor'] as $operation) {
                yield $status->value . '-' . $operation => [$status, $operation];
            }
        }
    }

    #[DataProvider('mutations')]
    public function testGuardRejectsMutationBeforeAnySideEffect(LicenseStatus $status, string $kind): void
    {
        $organization = (new Organization())->setName('Inactive')->setLicenseStatus($status);
        $user = (new User())->setOrganization($organization)->setRoles(['ROLE_ORG_ADMIN']);
        $storage = new TokenStorage();
        $storage->setToken(new UsernamePasswordToken($user, 'api', $user->getRoles()));
        $persist = $this->createMock(ProcessorInterface::class);
        $persist->expects(self::never())->method('process');
        $remove = $this->createMock(ProcessorInterface::class);
        $remove->expects(self::never())->method('process');
        $limits = $this->createMock(PlanLimitsService::class);
        $limits->expects(self::never())->method('checkPlantLimit');
        $limits->expects(self::never())->method('checkRoomLimit');
        $events = $this->createMock(PlantEventRepository::class);
        $events->expects(self::never())->method('appendEvent');

        [$processor, $entity] = match ($kind) {
            'plant' => [new PlantStateProcessor($persist, $storage, $events, new LicenseGuard(), $limits, $this->createMock(\Doctrine\ORM\EntityManagerInterface::class)), new Plant()],
            'room' => [new RoomStateProcessor($persist, $storage, new LicenseGuard(), $limits), new Room()],
            default => [new SensorStateProcessor($persist, $storage, new LicenseGuard(), $remove), new Sensor()],
        };

        $this->expectException(AccessDeniedException::class);
        $processor->process($entity, $kind === 'delete_sensor' ? new Delete() : new Patch(), [], ['previous_data' => clone $entity]);
    }
}
