<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Organization;
use App\Entity\RefreshToken;
use App\Entity\User;
use App\Enum\UserAccountStatus;
use Symfony\Component\HttpFoundation\Response;

final class AuthHardeningControllerTest extends ApiTestCase
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

    public function testPendingVerificationUserCannotObtainJwt(): void
    {
        $email = $this->uniqueEmail('pending');
        $user = $this->createActiveUser($email);
        $user->setAccountStatus(UserAccountStatus::PENDING_VERIFICATION);
        $this->entityManager->flush();

        $this->client->jsonRequest('POST', '/api/auth/login', [
            'email' => $email,
            'password' => 'StrongPass!123',
        ]);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    public function testSuspendedUserCannotObtainJwt(): void
    {
        $email = $this->uniqueEmail('suspended');
        $user = $this->createActiveUser($email);
        $user->setAccountStatus(UserAccountStatus::SUSPENDED);
        $this->entityManager->flush();

        $this->client->jsonRequest('POST', '/api/auth/login', [
            'email' => $email,
            'password' => 'StrongPass!123',
        ]);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    public function testLoginBruteForceBlocksAfterFiveFailures(): void
    {
        $email = $this->uniqueEmail('operator');
        $this->createActiveUser($email);
        $this->entityManager->flush();

        for ($attempt = 1; $attempt <= 5; ++$attempt) {
            $this->client->jsonRequest('POST', '/api/auth/login', [
                'email' => $email,
                'password' => 'WrongPass!123',
            ]);

            self::assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode(), 'Attempt ' . $attempt);
        }

        $this->client->jsonRequest('POST', '/api/auth/login', [
            'email' => $email,
            'password' => 'WrongPass!123',
        ]);

        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $this->client->getResponse()->getStatusCode());
        self::assertSame([
            'error' => 'Too many requests, please try again later.',
        ], json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testRotatingRefreshTokenRevokesThePreviousAccessToken(): void
    {
        $email = $this->uniqueEmail('operator2');
        $this->createActiveUser($email);
        $this->entityManager->flush();

        [$accessToken, $refreshToken] = $this->login($email, 'StrongPass!123');

        $this->client->jsonRequest('POST', '/api/auth/token/refresh', [
            'refreshToken' => $refreshToken,
        ]);

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $this->client->setServerParameter('HTTP_AUTHORIZATION', sprintf('Bearer %s', $accessToken));
        $this->client->request('GET', '/api/me', server: [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    public function testChangingPasswordRevokesRefreshTokensAndActiveAccessToken(): void
    {
        $email = $this->uniqueEmail('operator3');
        $this->createActiveUser($email);
        $this->entityManager->flush();

        [$accessToken] = $this->login($email, 'StrongPass!123');

        $this->client->setServerParameter('HTTP_AUTHORIZATION', sprintf('Bearer %s', $accessToken));
        $this->client->jsonRequest('POST', '/api/auth/change-password', [
            'currentPassword' => 'StrongPass!123',
            'newPassword' => 'Rotated-passphrase+789',
        ]);

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $this->client->request('GET', '/api/me', server: [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $accessToken),
        ]);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return array{0:string,1:string}
     */
    private function login(string $email, string $password): array
    {
        $this->client->jsonRequest('POST', '/api/auth/login', [
            'email' => $email,
            'password' => $password,
        ]);

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode(), (string) $this->client->getResponse()->getContent());

        $payload = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return [$payload['token'], $payload['refreshToken']];
    }

    private function createActiveUser(string $email): User
    {
        $organization = $this->createOrganization('Org ' . $email);
        $user = $this->createUser($organization, $email, 'StrongPass!123');
        $user->setAccountStatus(UserAccountStatus::ACTIVE);
        $user->setEmailVerifiedAt(new \DateTimeImmutable());
        $user->setPassword(password_hash('StrongPass!123', PASSWORD_BCRYPT));

        return $user;
    }

    private function uniqueEmail(string $prefix): string
    {
        return sprintf('%s_%s@cultivatrace.local', $prefix, bin2hex(random_bytes(4)));
    }
}
