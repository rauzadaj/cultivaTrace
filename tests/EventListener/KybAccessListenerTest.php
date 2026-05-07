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

    public function testRejectedLicenseBlocksReadAccess(): void
    {
        $org = $this->createOrganization('Rejected Org');
        $org->setLicenseStatus(LicenseStatus::REJECTED);
        $user = $this->createUser($org, 'rejected@test.local');
        $this->entityManager->flush();

        $this->authorizeClient($user);
        // GET is blocked by this listener; writes fall through to LicenseGuard.
        $this->client->request('GET', '/api/plants', [], [], [
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertStatusCode(Response::HTTP_FORBIDDEN);

        $body = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertStringContainsString('KYB', (string) ($body['error'] ?? ''));
    }

    public function testPendingLicenseAllowsReadAccess(): void
    {
        $org = $this->createOrganization('Pending Org');
        $org->setLicenseStatus(LicenseStatus::PENDING);
        $user = $this->createUser($org, 'pending-read@test.local');
        $this->entityManager->flush();

        $this->authorizeClient($user);
        $this->client->request('GET', '/api/plants', [], [], [
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        // KybAccessListener must NOT block reads for pending orgs
        self::assertNotSame(
            Response::HTTP_FORBIDDEN,
            $this->client->getResponse()->getStatusCode(),
            'Pending licence must not block read operations.',
        );
    }

    public function testActiveLicensePassesThroughKybCheck(): void
    {
        $org = $this->createOrganization('Active Org');
        $org->setLicenseStatus(LicenseStatus::ACTIVE);
        $user = $this->createUser($org, 'active@test.local');
        $this->entityManager->flush();

        $this->authorizeClient($user);
        $this->client->request('GET', '/api/plants', [], [], [
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        self::assertNotSame(
            Response::HTTP_FORBIDDEN,
            $this->client->getResponse()->getStatusCode(),
            'Active licence must not be blocked by KybAccessListener.',
        );
    }

    public function testKybStatusRouteIsExempt(): void
    {
        $org = $this->createOrganization('Rejected KYB Org');
        $org->setLicenseStatus(LicenseStatus::REJECTED);
        $user = $this->createUser($org, 'kyb-exempt@test.local');
        $this->entityManager->flush();

        $this->authorizeClient($user);
        $this->client->request('GET', '/api/kyb/status', [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        self::assertNotSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    public function testMeRouteIsExempt(): void
    {
        $org = $this->createOrganization('Rejected Me Org');
        $org->setLicenseStatus(LicenseStatus::REJECTED);
        $user = $this->createUser($org, 'me-exempt@test.local');
        $this->entityManager->flush();

        $this->authorizeClient($user);
        $this->client->request('GET', '/api/me', [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        self::assertNotSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    public function testLogoutRouteIsExempt(): void
    {
        $org = $this->createOrganization('Rejected Logout Org');
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
        $org = $this->createOrganization('Rejected Billing Org');
        $org->setLicenseStatus(LicenseStatus::REJECTED);
        $user = $this->createUser($org, 'billing-exempt@test.local');
        $this->entityManager->flush();

        $this->authorizeClient($user);
        $this->client->request('GET', '/api/billing/status', [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        self::assertNotSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }
}
