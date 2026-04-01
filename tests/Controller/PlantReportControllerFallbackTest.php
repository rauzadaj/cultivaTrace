<?php

namespace App\Tests\Controller;

use App\Controller\PlantReportController;
use App\Entity\Farm;
use App\Entity\Organization;
use App\Entity\Plant;
use App\Entity\Room;
use App\Entity\User;
use App\Repository\PlantEventRepository;
use App\Service\HashChainService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Sensiolabs\GotenbergBundle\Builder\BuilderInterface;
use Sensiolabs\GotenbergBundle\GotenbergPdfInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

#[RunTestsInSeparateProcesses]
final class PlantReportControllerFallbackTest extends TestCase
{
    public function testPlantReportReturns503WhenGotenbergIsUnavailable(): void
    {
        $gotenberg = new class() implements GotenbergPdfInterface {
            public function get(string $builder): BuilderInterface
            {
                throw new \BadMethodCallException();
            }

            public function html(): BuilderInterface
            {
                return new class() implements BuilderInterface {
                    public function content(string $template, array $context): self
                    {
                        return $this;
                    }

                    public function paperStandardSize(mixed $paperSize): self
                    {
                        return $this;
                    }

                    public function margins(float $top, float $bottom, float $left, float $right, mixed $unit = null): self
                    {
                        return $this;
                    }

                    public function generate(): object
                    {
                        throw new \RuntimeException('Gotenberg unavailable');
                    }
                };
            }

            public function url(): BuilderInterface { throw new \BadMethodCallException(); }
            public function markdown(): BuilderInterface { throw new \BadMethodCallException(); }
            public function office(): BuilderInterface { throw new \BadMethodCallException(); }
            public function merge(): BuilderInterface { throw new \BadMethodCallException(); }
            public function convert(): BuilderInterface { throw new \BadMethodCallException(); }
            public function split(): BuilderInterface { throw new \BadMethodCallException(); }
            public function flatten(): BuilderInterface { throw new \BadMethodCallException(); }
            public function encrypt(): BuilderInterface { throw new \BadMethodCallException(); }
            public function embed(): BuilderInterface { throw new \BadMethodCallException(); }
        };

        $plantEventRepository = $this->createMock(PlantEventRepository::class);
        $plantEventRepository->method('findByPlantOrderedAsc')->willReturn([]);

        $hashChain = $this->createMock(HashChainService::class);
        $hashChain->method('verify')->willReturn([
            'valid' => true,
            'broken_at' => null,
            'checked' => 0,
        ]);

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findBy')->willReturn([]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);

        $controller = new PlantReportController($gotenberg, $plantEventRepository, $hashChain, $entityManager, 'test');
        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn(true);

        $container = $this->createMock(ContainerInterface::class);
        $container
            ->method('has')
            ->willReturnCallback(static fn (string $id): bool => $id === 'security.authorization_checker');
        $container
            ->method('get')
            ->willReturnCallback(static fn (string $id): mixed => match ($id) {
                'security.authorization_checker' => $authorizationChecker,
                default => throw new \InvalidArgumentException(sprintf('Unexpected service "%s".', $id)),
            });

        $controller->setContainer($container);

        $organization = new Organization();
        $organization->setName('Org PDF');
        $organization->setCountry('FR');

        $user = new User();
        $user->setEmail('pdf@test.local');
        $user->setOrganization($organization);
        $user->setPassword('test');

        $farm = new Farm();
        $farm->setOrganization($organization);
        $farm->setTenantId($organization->getId());
        $farm->setName('Farm PDF');

        $room = new Room();
        $room->setFarm($farm);
        $room->setTenantId($organization->getId());
        $room->setName('Room PDF');
        $room->setCapacityMax(10);

        $plant = new Plant();
        $plant->setRoom($room);
        $plant->setTenantId($organization->getId());
        $plant->setCreatedBy($user);
        $plant->setGerminatedAt(new \DateTimeImmutable('2026-03-01'));

        $response = $controller->__invoke($plant);

        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
    }
}
