<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Organization;
use App\Entity\RefreshToken;
use App\Entity\User;
use App\Enum\LicenseStatus;
use Symfony\Component\HttpFoundation\Response;

final class LogoutControllerTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetSchema([
            Organization::class,
            User::class,
            RefreshToken::class,
        ]);
    }

    public function testLogoutRevokesAllActiveRefreshTokensAndReturns204(): void
    {
        $org = $this->createOrganization('Logout Org');
        $org->setLicenseStatus(LicenseStatus::ACTIVE);
        $user = $this->createUser($org, 'logout@test.local');

        // Create two active refresh tokens for this user
        foreach (['hash-a', 'hash-b'] as $hash) {
            $token = new RefreshToken();
            $token->setUser($user);
            $token->setTokenHash($hash);
            $token->setExpiresAt(new \DateTimeImmutable('+7 days'));
            $this->entityManager->persist($token);
        }

        $this->entityManager->flush();

        $this->authorizeClient($user);
        $this->client->request('POST', '/api/auth/logout', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT'  => 'application/json',
        ]);

        $this->assertStatusCode(Response::HTTP_NO_CONTENT);

        $this->entityManager->clear();

        $unrevoked = (int) $this->entityManager
            ->createQuery('SELECT COUNT(rt.id) FROM App\Entity\RefreshToken rt WHERE rt.user = :user AND rt.revokedAt IS NULL')
            ->setParameter('user', $user)
            ->getSingleScalarResult();

        self::assertSame(0, $unrevoked, 'All refresh tokens must be revoked after logout.');
    }

    public function testLogoutWithoutAuthenticationReturns401(): void
    {
        $this->client->request('POST', '/api/auth/logout', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT'  => 'application/json',
        ]);

        $this->assertStatusCode(Response::HTTP_UNAUTHORIZED);
    }

    public function testLogoutIsIdempotentWhenNoTokensExist(): void
    {
        $org = $this->createOrganization('Empty Token Org');
        $org->setLicenseStatus(LicenseStatus::ACTIVE);
        $user = $this->createUser($org, 'no-tokens@test.local');
        $this->entityManager->flush();

        $this->authorizeClient($user);
        $this->client->request('POST', '/api/auth/logout', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT'  => 'application/json',
        ]);

        $this->assertStatusCode(Response::HTTP_NO_CONTENT);
    }
}
