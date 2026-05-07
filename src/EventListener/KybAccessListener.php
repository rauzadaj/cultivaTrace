<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use App\Enum\LicenseStatus;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Blocks API access for organizations whose KYB licence was explicitly rejected.
 *
 * Lifecycle of licence status:
 *   PENDING   → read-only (LicenseGuard in state processors blocks writes individually)
 *   REJECTED  → full block — licence was denied, no access until re-submitted and approved
 *   SUSPENDED → blocked by TenantListener (priority -10, runs before this listener)
 *   EXPIRED   → blocked by TenantListener
 *   ACTIVE    → no restriction
 *
 * Runs after TenantListener (priority -10) so the tenant filter is already set.
 * Exempt routes (auth, kyb, billing, me) bypass the check entirely.
 */
final class KybAccessListener
{
    /** Routes whose prefix bypasses the KYB check. */
    private const EXEMPT_PREFIXES = [
        '/api/auth/',
        '/api/kyb/',
        '/api/billing/',
        '/api/register/',
    ];

    /** Routes matched exactly that bypass the KYB check. */
    private const EXEMPT_EXACT = [
        '/api/me',
        '/api/health',
    ];

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();

        if (in_array($path, self::EXEMPT_EXACT, true)) {
            return;
        }

        foreach (self::EXEMPT_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return;
            }
        }

        $token = $this->tokenStorage->getToken();
        if ($token === null) {
            return;
        }

        $user = $token->getUser();
        if (!$user instanceof User || !$user->hasOrganization()) {
            return;
        }

        if ($user->getOrganization()->getLicenseStatus() !== LicenseStatus::REJECTED) {
            return;
        }

        $method = $event->getRequest()->getMethod();
        if (!in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            // Writes fall through to LicenseGuard in state processors, which returns
            // the API Platform problem-detail format expected by existing tests.
            return;
        }

        $event->setResponse(new JsonResponse(
            ['error' => 'KYB licence rejected. Read access is restricted until a valid licence is submitted and approved.'],
            Response::HTTP_FORBIDDEN,
        ));
    }
}
