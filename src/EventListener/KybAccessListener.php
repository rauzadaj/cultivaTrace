<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use App\Enum\LicenseStatus;
use App\Service\License\LicenseGuard;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Centralised KYB access enforcement for all non-ACTIVE organisations.
 *
 * Licence lifecycle enforcement matrix (this listener runs at priority -20,
 * after TenantListener at -10 which already handles SUSPENDED / EXPIRED):
 *
 *   PENDING  + unsafe method (POST/PUT/PATCH/DELETE) → 403 via LicenseGuard
 *   PENDING  + safe  method (GET/HEAD/OPTIONS)       → pass (read-only access)
 *   REJECTED + unsafe method                         → 403 via LicenseGuard
 *   REJECTED + safe  method                          → 403 (no access at all)
 *   SUSPENDED / EXPIRED                              → blocked by TenantListener before this runs
 *   ACTIVE                                           → pass
 *
 * Delegating unsafe-method enforcement to LicenseGuard::assertCanWrite() ensures
 * the response uses the same API-Platform problem-detail format (status/detail)
 * that processors and existing tests already rely on, avoiding two divergent 403
 * shapes in the API.
 *
 * Exempt paths (auth, kyb, billing, register, me, health) bypass the check so
 * users can always submit KYB documents, manage billing, and call /me regardless
 * of licence status.
 */
final class KybAccessListener
{
    private const EXEMPT_PREFIXES = [
        '/api/auth/',
        '/api/kyb/',
        '/api/billing/',
        '/api/register/',
    ];

    private const EXEMPT_EXACT = [
        '/api/me',
        '/api/health',
    ];

    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly LicenseGuard $licenseGuard,
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

        $org = $user->getOrganization();
        $status = $org->getLicenseStatus();

        if ($status === LicenseStatus::ACTIVE) {
            return;
        }

        $isSafeMethod = in_array($event->getRequest()->getMethod(), self::SAFE_METHODS, true);

        if (!$isSafeMethod) {
            // Centralised write block for PENDING and REJECTED.
            // assertCanWrite() throws AccessDeniedException, which API Platform
            // converts to a 403 problem-detail response (status + detail fields).
            // This also supersedes individual LicenseGuard calls in state processors
            // that only guarded create paths (e.g. PlantStateProcessor skipped PATCH).
            $this->licenseGuard->assertCanWrite($org);

            return;
        }

        // Safe methods: REJECTED blocks all reads too.
        // PENDING is read-only — safe methods pass through.
        if ($status === LicenseStatus::REJECTED) {
            throw new AccessDeniedException(
                sprintf('Tenant licence status "%s" does not allow access.', $status->value),
            );
        }
    }
}
