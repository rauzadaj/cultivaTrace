<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Organization;
use App\Entity\User;
use Symfony\Component\HttpFoundation\Response;

final class MercureTokenControllerTest extends ApiTestCase
{
    protected function setUp(): void
    {
        $_ENV['MERCURE_JWT_SECRET'] = '!ChangeThisMercureHubJWTSecretKey!';
        $_SERVER['MERCURE_JWT_SECRET'] = '!ChangeThisMercureHubJWTSecretKey!';

        parent::setUp();

        $this->resetSchema([
            Organization::class,
            User::class,
        ]);
    }

    public function testMercureTokenIsRestrictedToAuthenticatedUsersTenant(): void
    {
        $organizationA = $this->createOrganization('Tenant A');
        $organizationB = $this->createOrganization('Tenant B');
        $userA = $this->createUser($organizationA, 'tenant-a@test.local');
        $userB = $this->createUser($organizationB, 'tenant-b@test.local');
        $this->entityManager->flush();

        $this->authorizeClient($userA);
        $this->client->request('GET', '/api/mercure/token');

        $this->assertStatusCode(Response::HTTP_OK);

        $payloadA = $this->decodeJwtPayload(
            json_decode($this->client->getResponse()->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR)['token'] ?? ''
        );

        $this->authorizeClient($userB);
        $this->client->request('GET', '/api/mercure/token');

        $this->assertStatusCode(Response::HTTP_OK);

        $payloadB = $this->decodeJwtPayload(
            json_decode($this->client->getResponse()->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR)['token'] ?? ''
        );

        $frontendUrl = rtrim($_SERVER['FRONTEND_URL'] ?? $_ENV['FRONTEND_URL'] ?? 'http://localhost:5173', '/');

        self::assertSame(
            [sprintf('%s/tenants/%s/*', $frontendUrl, $organizationA->getId())],
            $payloadA['mercure']['subscribe'] ?? null,
        );
        self::assertSame(
            [sprintf('%s/tenants/%s/*', $frontendUrl, $organizationB->getId())],
            $payloadB['mercure']['subscribe'] ?? null,
        );
        self::assertNotContains(
            sprintf('%s/tenants/%s/*', $frontendUrl, $organizationB->getId()),
            $payloadA['mercure']['subscribe'] ?? [],
        );
        self::assertNotContains(
            sprintf('%s/tenants/%s/*', $frontendUrl, $organizationA->getId()),
            $payloadB['mercure']['subscribe'] ?? [],
        );
    }

    public function testMercureTokenEndpointReturns401WithoutJwt(): void
    {
        $this->client->request('GET', '/api/mercure/token');

        $this->assertStatusCode(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJwtPayload(string $token): array
    {
        $parts = explode('.', $token, 3);
        $payload = $parts[1] ?? '';
        $normalizedPayload = strtr($payload, '-_', '+/');
        $normalizedPayload = str_pad($normalizedPayload, (int) ceil(strlen($normalizedPayload) / 4) * 4, '=', STR_PAD_RIGHT);

        return json_decode(base64_decode($normalizedPayload, true) ?: '{}', true, 512, JSON_THROW_ON_ERROR);
    }
}
