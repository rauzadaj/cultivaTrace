<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\RateLimit;

final class ApiRateLimitSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RateLimiterFactory $apiGlobalLimiter,
        private readonly RateLimiterFactory $apiAuthLoginLimiter,
        private readonly RateLimiterFactory $apiAuthRefreshLimiter,
        private readonly RateLimiterFactory $apiKybWriteLimiter,
        private readonly RateLimiterFactory $apiBillingWriteLimiter,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 20]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        // Exclure le webhook Stripe — Stripe a ses propres retry policies
        if ($request->getPathInfo() === '/api/billing/webhook') {
            return;
        }

        $specificFactory = $this->resolveSpecificLimiter($request);
        if ($specificFactory instanceof RateLimiterFactory) {
            $specificLimit = $specificFactory->create($this->buildSpecificLimiterKey($request))->consume(1);
            if (!$specificLimit->isAccepted()) {
                $event->setResponse($this->createRateLimitResponse($specificLimit));

                return;
            }
        }

        $limit = $this->apiGlobalLimiter->create($this->buildGlobalLimiterKey($request))->consume(1);

        if (!$limit->isAccepted()) {
            $event->setResponse($this->createRateLimitResponse($limit));
        }
    }

    private function resolveSpecificLimiter(\Symfony\Component\HttpFoundation\Request $request): ?RateLimiterFactory
    {
        if (!$request->isMethod('POST')) {
            return null;
        }

        $path = $request->getPathInfo();

        return match (true) {
            $path === '/api/auth/login' => $this->apiAuthLoginLimiter,
            $path === '/api/auth/token/refresh' => $this->apiAuthRefreshLimiter,
            $path === '/api/kyb/upload' || str_starts_with($path, '/api/kyb/admin/validate/') => $this->apiKybWriteLimiter,
            str_starts_with($path, '/api/billing/') => $this->apiBillingWriteLimiter,
            default => null,
        };
    }

    private function createRateLimitResponse(RateLimit $limit): JsonResponse
    {
        return new JsonResponse(
            ['error' => 'Too many requests.'],
            Response::HTTP_TOO_MANY_REQUESTS,
            ['X-RateLimit-Retry-After' => (string) $limit->getRetryAfter()?->getTimestamp()],
        );
    }

    private function buildSpecificLimiterKey(\Symfony\Component\HttpFoundation\Request $request): string
    {
        $ip = (string) ($request->getClientIp() ?? 'unknown');

        if ($request->isMethod('POST') && $request->getPathInfo() === '/api/auth/login') {
            $email = $this->extractPayloadValue($request, 'email');

            return $email !== null ? sprintf('login:%s', mb_strtolower($email)) : $ip;
        }

        return $ip;
    }

    private function buildGlobalLimiterKey(\Symfony\Component\HttpFoundation\Request $request): string
    {
        return (string) ($request->getClientIp() ?? 'unknown');
    }

    private function extractPayloadValue(\Symfony\Component\HttpFoundation\Request $request, string $field): ?string
    {
        try {
            $payload = [] === $request->request->all()
                ? ($request->getContent() !== '' ? $request->toArray() : [])
                : $request->request->all();
        } catch (\JsonException) {
            return null;
        }

        $value = trim((string) ($payload[$field] ?? ''));

        return $value !== '' ? $value : null;
    }
}
