<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Organization;
use App\Entity\OrganizationInvitation;
use App\Entity\User;
use App\Service\Auth\TokenHasher;
use Symfony\Component\HttpFoundation\Response;

final class AcceptOrganizationInvitationControllerTest extends ApiTestCase
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

    public function testAcceptingInvitationCreatesAnActiveUser(): void
    {
        $organization = $this->createOrganization('Org Invite');
        $admin = $this->createUser($organization, 'admin@cultivatrace.local', role: 'ROLE_ORG_ADMIN');
        $tokenHasher = self::getContainer()->get(TokenHasher::class);
        $plainToken = 'plain-invite-token';

        $invitation = (new OrganizationInvitation())
            ->setOrganization($organization)
            ->setInvitedBy($admin)
            ->setEmail('invitee@cultivatrace.local')
            ->setRoles(['ROLE_ORG_USER'])
            ->setTokenHash($tokenHasher->hash($plainToken));

        $this->entityManager->persist($invitation);
        $this->entityManager->flush();

        $this->client->jsonRequest('POST', '/api/register/invitation/accept', [
            'token' => $plainToken,
            'password' => 'StrongPass!123',
        ]);

        self::assertSame(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode(), (string) $this->client->getResponse()->getContent());

        /** @var User|null $invitedUser */
        $invitedUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'invitee@cultivatrace.local']);
        self::assertInstanceOf(User::class, $invitedUser);
        self::assertSame(['ROLE_ORG_USER'], $invitedUser->getRoles());
        self::assertNotNull($invitedUser->getEmailVerifiedAt());
    }

    public function testInvitationAcceptanceIsRateLimited(): void
    {
        // Keep the same kernel across requests so the in-memory limiter storage
        // (app.test.rate_limiter.storage) persists between attempts.
        $this->client->disableReboot();

        // The api_register_invitation limiter allows 5 attempts per window.
        // Attempts with a bad token are rejected at the token stage (422) but
        // still consume the limiter, so the 6th attempt must be throttled (429).
        for ($attempt = 1; $attempt <= 5; ++$attempt) {
            $this->client->jsonRequest('POST', '/api/register/invitation/accept', [
                'token' => 'invalid-token',
                'password' => 'StrongPass!234',
            ]);
            self::assertSame(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                $this->client->getResponse()->getStatusCode(),
                (string) $this->client->getResponse()->getContent(),
            );
        }

        $this->client->jsonRequest('POST', '/api/register/invitation/accept', [
            'token' => 'invalid-token',
            'password' => 'StrongPass!234',
        ]);

        self::assertSame(
            Response::HTTP_TOO_MANY_REQUESTS,
            $this->client->getResponse()->getStatusCode(),
            (string) $this->client->getResponse()->getContent(),
        );
    }
}
