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
}
