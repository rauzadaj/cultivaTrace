<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Http\EventSubscriber;

use App\Infrastructure\Http\EventSubscriber\ApiRateLimitSubscriber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

final class ApiRateLimitSubscriberTest extends TestCase
{
    #[DataProvider('specificLimiterProvider')]
    public function testSpecificSensitiveRoutesAreRateLimited(string $path, string $limiterId): void
    {
        $loginLimiter = $this->createLimiterFactory('api_auth_login', 10);
        $refreshLimiter = $this->createLimiterFactory('api_auth_refresh', 20);
        $kybLimiter = $this->createLimiterFactory('api_kyb_write', 30);
        $billingLimiter = $this->createLimiterFactory('api_billing_write', 50);
        $globalLimiter = $this->createLimiterFactory('api_global', 300);

        $targetFactory = match ($limiterId) {
            'api_auth_login' => $loginLimiter,
            'api_auth_refresh' => $refreshLimiter,
            'api_kyb_write' => $kybLimiter,
            'api_billing_write' => $billingLimiter,
        };
        $this->exhaustLimiter($targetFactory, '203.0.113.10', match ($limiterId) {
            'api_auth_login' => 10,
            'api_auth_refresh' => 20,
            'api_kyb_write' => 30,
            'api_billing_write' => 50,
        });

        $subscriber = new ApiRateLimitSubscriber(
            $globalLimiter,
            $loginLimiter,
            $refreshLimiter,
            $kybLimiter,
            $billingLimiter,
        );

        $request = Request::create($path, 'POST', server: ['REMOTE_ADDR' => '203.0.113.10']);
        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $event->getResponse()?->getStatusCode());
        self::assertSame(['error' => 'Too many requests.'], json_decode((string) $event->getResponse()?->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testBillingWebhookIsExcludedFromRateLimiting(): void
    {
        $subscriber = new ApiRateLimitSubscriber(
            $this->createLimiterFactory('api_global', 0),
            $this->createLimiterFactory('api_auth_login', 0),
            $this->createLimiterFactory('api_auth_refresh', 0),
            $this->createLimiterFactory('api_kyb_write', 0),
            $this->createLimiterFactory('api_billing_write', 0),
        );

        $request = Request::create('/api/billing/webhook', 'POST', server: ['REMOTE_ADDR' => '203.0.113.10']);
        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testRefreshLimiterUsesStableClientKeyAcrossTokenChanges(): void
    {
        $globalLimiter = $this->createLimiterFactory('api_global', 300);
        $refreshLimiter = $this->createLimiterFactory('api_auth_refresh', 1);
        $subscriber = new ApiRateLimitSubscriber(
            $globalLimiter,
            $this->createLimiterFactory('api_auth_login', 10),
            $refreshLimiter,
            $this->createLimiterFactory('api_kyb_write', 30),
            $this->createLimiterFactory('api_billing_write', 50),
        );

        $firstEvent = $this->createPostRequestEvent(
            '/api/auth/token/refresh',
            ['refreshToken' => 'first-token'],
            '203.0.113.10',
        );
        $subscriber->onKernelRequest($firstEvent);
        self::assertNull($firstEvent->getResponse());

        $secondEvent = $this->createPostRequestEvent(
            '/api/auth/token/refresh',
            ['refreshToken' => 'second-token'],
            '203.0.113.10',
        );
        $subscriber->onKernelRequest($secondEvent);

        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $secondEvent->getResponse()?->getStatusCode());
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function specificLimiterProvider(): iterable
    {
        yield 'login' => ['/api/auth/login', 'api_auth_login'];
        yield 'refresh' => ['/api/auth/token/refresh', 'api_auth_refresh'];
        yield 'kyb upload' => ['/api/kyb/upload', 'api_kyb_write'];
        yield 'kyb admin validate' => ['/api/kyb/admin/validate/123', 'api_kyb_write'];
        yield 'billing checkout' => ['/api/billing/checkout', 'api_billing_write'];
    }

    private function createLimiterFactory(string $id, int $limit): RateLimiterFactory
    {
        return new RateLimiterFactory([
            'id' => $id,
            'policy' => 'fixed_window',
            'limit' => $limit,
            'interval' => '15 minutes',
        ], new InMemoryStorage());
    }

    private function exhaustLimiter(RateLimiterFactory $factory, string $key, int $attempts): void
    {
        $factory->create($key)->consume($attempts);
    }

    /**
     * @param array<string, string> $payload
     */
    private function createPostRequestEvent(string $path, array $payload, string $ip): RequestEvent
    {
        $request = Request::create(
            $path,
            'POST',
            server: ['REMOTE_ADDR' => $ip],
            content: json_encode($payload, JSON_THROW_ON_ERROR),
        );
        $request->headers->set('CONTENT_TYPE', 'application/json');

        return new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
    }
}
