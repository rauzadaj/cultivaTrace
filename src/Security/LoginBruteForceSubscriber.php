<?php

declare(strict_types=1);

namespace App\Security;

use App\Service\Auth\LoginAttemptService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class LoginBruteForceSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private LoginAttemptService $loginAttemptService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if ($request->getPathInfo() !== '/api/auth/login' || !$request->isMethod('POST')) {
            return;
        }

        $email = $this->extractEmail($request);

        if ($email === null || !$this->loginAttemptService->isBlocked($email)) {
            return;
        }

        $event->setResponse(new JsonResponse([
            'error' => 'Too many requests, please try again later.',
        ], Response::HTTP_TOO_MANY_REQUESTS));
    }

    private function extractEmail(\Symfony\Component\HttpFoundation\Request $request): ?string
    {
        try {
            $payload = $request->getContent() !== '' ? $request->toArray() : [];
        } catch (\JsonException) {
            return null;
        }

        $email = trim((string) ($payload['email'] ?? ''));

        return $email !== '' ? mb_strtolower($email) : null;
    }
}
