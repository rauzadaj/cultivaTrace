<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\PlantEvent;
use App\Repository\PlantEventRepository;
use App\Service\HashChainService;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class HashChainVerificationTest extends TestCase
{
    public static function corruptions(): iterable
    {
        yield 'initial zero hash' => [0, 'previous'];
        yield 'stored predecessor' => [1, 'previous'];
        yield 'hashSelf' => [1, 'self'];
        yield 'payload' => [1, 'payload'];
    }

    /** @param 'previous'|'self'|'payload' $field */
    #[DataProvider('corruptions')]
    public function testVerifierDetectsCorruption(int $index, string $field): void
    {
        [$chain, $events] = $this->chain();
        // Corruption of an in-memory fixture only; never bypass the SQL/ORM protections.
        match ($field) {
            'previous' => $events[$index]->setHashPrevious(str_repeat('f', 64)),
            'self' => $events[$index]->setHashSelf(str_repeat('f', 64)),
            'payload' => $events[$index]->setPayload(['message' => 'changed']),
        };
        self::assertSame(['valid' => false, 'broken_at' => (string) $events[$index]->getId(), 'checked' => $index], $chain->verify(Uuid::v4()));
    }

    public function testUnchangedHistoricalHashFormatAndValidChain(): void
    {
        [$chain, $events] = $this->chain();
        foreach ($events as $event) {
            $historicInput = implode('|', [(string) $event->getId(), '{"message":"échantillon"}', '1770000000', $event->getHashPrevious()]);
            self::assertSame(hash('sha256', $historicInput), $event->getHashSelf());
        }
        self::assertSame(['valid' => true, 'broken_at' => null, 'checked' => 2], $chain->verify(Uuid::v4()));
    }

    /** @return array{HashChainService, list<PlantEvent>} */
    private function chain(): array
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $repository = $this->createMock(PlantEventRepository::class);
        $registry->method('getRepository')->willReturn($repository);
        $chain = new HashChainService($registry);
        $events = [];
        $previous = str_repeat('0', 64);
        for ($i = 0; $i < 2; ++$i) {
            $event = (new PlantEvent())->setPayload(['message' => 'échantillon'])->setOccurredAt(new \DateTimeImmutable('@1770000000'))->setHashPrevious($previous);
            $previous = $chain->computeHash($event, $previous);
            $event->setHashSelf($previous);
            $events[] = $event;
        }
        $repository->method('findByPlantOrderedAsc')->willReturn($events);
        return [$chain, $events];
    }
}
