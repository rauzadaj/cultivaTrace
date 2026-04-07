<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\VpdService;
use PHPUnit\Framework\TestCase;

final class VpdServiceTest extends TestCase
{
    private VpdService $service;

    protected function setUp(): void
    {
        $this->service = new VpdService();
    }

    public function testComputeReturnsExpectedKpa(): void
    {
        self::assertSame(1.27, $this->service->compute(25.0, 60.0));
    }

    public function testEvaluateReturnsTooLowForVegetation(): void
    {
        $result = $this->service->evaluate(0.3, 'vegetation');

        self::assertSame('too_low', $result['status']);
    }

    public function testEvaluateReturnsOptimalForVegetation(): void
    {
        $result = $this->service->evaluate(1.0, 'vegetation');

        self::assertSame('optimal', $result['status']);
    }

    public function testEvaluateReturnsTooHighForVegetation(): void
    {
        $result = $this->service->evaluate(1.8, 'vegetation');

        self::assertSame('too_high', $result['status']);
    }
}
