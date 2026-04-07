<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Organization;
use App\Entity\OrganizationInvitation;
use App\Entity\User;
use Symfony\Component\HttpFoundation\Response;

final class OrganizationAdminControllerTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetSchema([
            Organization::class,
            User::class,
            OrganizationInvitation::class,
        ]);
    }

    public function testOrgAdminCanReadAndUpdateOrganizationSettings(): void
    {
        $organization = $this->createOrganization('Org Admin', 'CA');
        $admin = $this->createUser($organization, 'admin@cultivatrace.local', role: 'ROLE_ORG_ADMIN');
        $this->entityManager->flush();

        $this->authorizeClient($admin);
        $this->client->request('GET', '/api/organization/settings', server: [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $payload = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Org Admin', $payload['name']);
        self::assertSame('CA', $payload['country']);

        $this->client->jsonRequest('PATCH', '/api/organization/settings', [
            'name' => 'Org Admin Updated',
            'contactEmail' => 'ops@cultivatrace.local',
            'config' => ['timezone' => 'Europe/Paris'],
        ]);

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $updatedPayload = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Org Admin Updated', $updatedPayload['name']);
        self::assertSame('ops@cultivatrace.local', $updatedPayload['contactEmail']);
        self::assertSame('Europe/Paris', $updatedPayload['config']['timezone']);
    }

    public function testOrgAdminCanInviteMemberAndListPendingInvitations(): void
    {
        $organization = $this->createOrganization('Org Invite');
        $admin = $this->createUser($organization, 'admin@cultivatrace.local', role: 'ROLE_ORG_ADMIN');
        $this->entityManager->flush();

        $this->authorizeClient($admin);
        $this->client->jsonRequest('POST', '/api/organization/invitations', [
            'email' => 'new-member@cultivatrace.local',
            'role' => 'ROLE_ORG_USER',
        ]);

        self::assertSame(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode(), (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/api/organization/invitations', server: [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $payload = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertCount(1, $payload['invitations']);
        self::assertSame('new-member@cultivatrace.local', $payload['invitations'][0]['email']);
    }

    public function testOrgUserCannotAccessOrganizationAdminEndpoints(): void
    {
        $organization = $this->createOrganization('Org User');
        $user = $this->createUser($organization, 'user@cultivatrace.local', role: 'ROLE_ORG_USER');
        $this->entityManager->flush();

        $this->authorizeClient($user);
        $this->client->request('GET', '/api/organization/settings', server: [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        self::assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }
}
