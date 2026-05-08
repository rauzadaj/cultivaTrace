<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Entity\Organization;
use App\Entity\User;
use App\Enum\LicenseStatus;
use App\Tests\Controller\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

final class KybAccessListenerTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetSchema([
            Organization::class,
            User::class,
        ]);
    }

    // ── REJECTED status ───────────────────────────────────────────────────────

    public function testRejectedLicenseBlocksReadAccess(): void
    {
        $org = $this->createOrganization('Rejected Org');
        $org->setLicenseStatus(LicenseStatus::REJECTED);
        $user = $this->createUser($org, 'rejected-read@test.local');
        $this->entityManager->flush();

        $this->authorizeClient($user);
        $this->client->request('GET', '/api/plants', [], [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertStatusCode(Response::HTTP_FORBIDDEN);
        $body = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertStringContainsString('rejected', (string) ($body['detail'] ?? ''));
    }

    // ── PENDING status ────────────────────────────────────────────────────────

    public function testPendingLicenseAllowsReadAccess(): void
    {
        $org = $this->createOrganization('Pending Org');
        $org->setLicenseStatus(LicenseStatus::PENDING);
        $user = $this->createUser($org, 'pending-read@test.local');
        $this->entityManager->flush();

        $this->authorizeClient($user);
        $this->client->request('GET', '/api/plants', [], [], ['HTTP_ACCEPT' => 'application/ld+json']);

        self::assertNotSame(
            Response::HTTP_FORBIDDEN,
            $this->client->getResponse()->getStatusCode(),
            'Pending licence must not block read operations.',
        );
    }

    public function testPendingLicenseBlocksWriteAccess(): void
    {
        $org = $this->createOrganization('Pending Write Org');
        $org->setLicenseStatus(LicenseStatus::PENDING);
        $user = $this->createUser($org, 'pending-write@test.local', role: 'ROLE_ORG_ADMIN');
        $this->entityManager->flush();

        $this->authorizeClient($user);
        // PATCH (unsafe method) must be blocked centrally — previously
        // PlantStateProcessor skipped assertLicenseApproved on update paths.
        $this->apiJsonRequest('PATCH', '/api/plants/nonexistent-id', []);

        self::assertSame(
            Response::HTTP_FORBIDDEN,
            $this->client->getResponse()->getStatusCode(),
            'Pending licence must block unsafe HTTP methods.',
        );

        $body = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertStringContainsString('pending', (string) ($body['detail'] ?? ''));
    }

    // ── SUSPENDED status ──────────────────────────────────────────────────────

    public function testSuspendedLicenseBlocksAllAccess(): void
    {
        $org = $this->createOrganization('Suspended Org');
        $org->setLicenseStatus(LicenseStatus::SUSPENDED);
        $user = $this->createUser($org, 'suspended@test.local');
        $this->entityManager->flush();

        $this->authorizeClient($user);
        // SUSPENDED orgs are blocked by the UserChecker before the request is dispatched,
        // resulting in 401 (authentication failure) rather than 403.
        $this->client->request('GET', '/api/plants', [], [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $status = $this->client->getResponse()->getStatusCode();
        self::assertContains(
            $status,
            [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN],
            'Suspended org must not have access (expected 401 or 403).',
        );
    }

    public function testSuspendedLicenseBlocksWriteAccess(): void
    {
        $org = $this->createOrganization('Suspended Write Org');
        $org->setLicenseStatus(LicenseStatus::SUSPENDED);
        $user = $this->createUser($org, 'suspended-write@test.local', role: 'ROLE_ORG_ADMIN');
        $this->entityManager->flush();

        $this->authorizeClient($user);
        $this->apiJsonRequest('PATCH', '/api/plants/nonexistent-id', []);

        $status = $this->client->getResponse()->getStatusCode();
        self::assertContains(
            $status,
            [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN],
            'Suspended org write must not succeed (expected 401 or 403).',
        );
    }

    // ── ACTIVE status ─────────────────────────────────────────────────────────

    public function testActiveLicensePassesThroughKybCheck(): void
    {
        $org = $this->createOrganization('Active Org');
        $org->setLicenseStatus(LicenseStatus::ACTIVE);
        $user = $this->createUser($org, 'active@test.local');
        $this->entityManager->flush();

        $this->authorizeClient($user);
        $this->client->request('GET', '/api/plants', [], [], ['HTTP_ACCEPT' => 'application/ld+json']);

        self::assertNotSame(
            Response::HTTP_FORBIDDEN,
            $this->client->getResponse()->getStatusCode(),
            'Active licence must not be blocked by KybAccessListener.',
        );
    }

    // ── Exempt routes ─────────────────────────────────────────────────────────

    public function testKybStatusRouteIsExempt(): void
    {
        $org = $this->createOrganization('Rejected KYB Exempt Org');
        $org->setLicenseStatus(LicenseStatus::REJECTED);
        $user = $this->createUser($org, 'kyb-exempt@test.local');
        $this->entityManager->flush();

        $this->authorizeClient($user);
        $this->client->request('GET', '/api/kyb/status', [], [], ['HTTP_ACCEPT' => 'application/json']);

        self::assertNotSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    public function testMeRouteIsExempt(): void
    {
        $org = $this->createOrganization('Rejected Me Exempt Org');
        $org->setLicenseStatus(LicenseStatus::REJECTED);
        $user = $this->createUser($org, 'me-exempt@test.local');
        $this->entityManager->flush();

        $this->authorizeClient($user);
        $this->client->request('GET', '/api/me', [], [], ['HTTP_ACCEPT' => 'application/json']);

        self::assertNotSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    public function testLogoutRouteIsExempt(): void
    {
        $org = $this->createOrganization('Rejected Logout Exempt Org');
        $org->setLicenseStatus(LicenseStatus::REJECTED);
        $user = $this->createUser($org, 'logout-exempt@test.local');
        $this->entityManager->flush();

        $this->authorizeClient($user);
        $this->client->request('POST', '/api/auth/logout', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT'  => 'application/json',
        ]);

        self::assertNotSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    public function testBillingRouteIsExempt(): void
    {
        $org = $this->createOrganization('Rejected Billing Exempt Org');
        $org->setLicenseStatus(LicenseStatus::REJECTED);
        $user = $this->createUser($org, 'billing-exempt@test.local');
        $this->entityManager->flush();

        $this->authorizeClient($user);
        $this->client->request('GET', '/api/billing/status', [], [], ['HTTP_ACCEPT' => 'application/json']);

        self::assertNotSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }
}
