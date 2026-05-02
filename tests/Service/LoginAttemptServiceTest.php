<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\Auth\LoginAttemptService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class LoginAttemptServiceTest extends TestCase
{
    private LoginAttemptService $service;

    protected function setUp(): void
    {
        $this->service = new LoginAttemptService(new ArrayAdapter());
    }

    public function testNotBlockedInitially(): void
    {
        self::assertFalse($this->service->isBlocked('user@test.local'));
    }

    public function testNotBlockedAfterFourFailures(): void
    {
        // Use future dates so getState's real-clock comparison doesn't expire our test block
        $now = new \DateTimeImmutable('+2 years');
        for ($i = 0; $i < 4; $i++) {
            $this->service->recordFailure('user@test.local', $now);
        }

        self::assertFalse($this->service->isBlocked('user@test.local', $now));
    }

    public function testBlockedAfterFiveFailures(): void
    {
        $now = new \DateTimeImmutable('+2 years');
        for ($i = 0; $i < 5; $i++) {
            $this->service->recordFailure('user@test.local', $now);
        }

        self::assertTrue($this->service->isBlocked('user@test.local', $now));
    }

    public function testBlockExpireAfterFifteenMinutes(): void
    {
        $lockTime = new \DateTimeImmutable('+2 years');
        for ($i = 0; $i < 5; $i++) {
            $this->service->recordFailure('user@test.local', $lockTime);
        }

        $afterExpiry = $lockTime->modify('+15 minutes +1 second');
        self::assertFalse($this->service->isBlocked('user@test.local', $afterExpiry));
    }

    public function testClearResetsBlock(): void
    {
        $now = new \DateTimeImmutable('+2 years');
        for ($i = 0; $i < 5; $i++) {
            $this->service->recordFailure('user@test.local', $now);
        }

        self::assertTrue($this->service->isBlocked('user@test.local', $now));

        $this->service->clear('user@test.local');

        self::assertFalse($this->service->isBlocked('user@test.local', $now));
    }

    public function testEmailIsCaseInsensitive(): void
    {
        $now = new \DateTimeImmutable('+2 years');
        for ($i = 0; $i < 5; $i++) {
            $this->service->recordFailure('USER@TEST.LOCAL', $now);
        }

        self::assertTrue($this->service->isBlocked('user@test.local', $now));
    }

    public function testSecondBlockDoesNotExtendBlockTime(): void
    {
        $lockTime = new \DateTimeImmutable('+2 years');
        for ($i = 0; $i < 5; $i++) {
            $this->service->recordFailure('user@test.local', $lockTime);
        }

        // Additional failures after being blocked should not extend the block
        $later = $lockTime->modify('+5 minutes');
        $this->service->recordFailure('user@test.local', $later);

        $afterOriginalExpiry = $lockTime->modify('+15 minutes +1 second');
        self::assertFalse($this->service->isBlocked('user@test.local', $afterOriginalExpiry));
    }
}
