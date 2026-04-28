<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Alert;
use App\Entity\Farm;
use App\Entity\Organization;
use App\Entity\Room;
use App\Entity\Sensor;
use App\Entity\User;
use App\Enum\LicenseStatus;
use Symfony\Component\HttpFoundation\Response;

final class AlertControllerTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetSchema([
            Organization::class,
            User::class,
            Farm::class,
            Room::class,
            Sensor::class,
            Alert::class,
        ]);
    }

    public function testListReturnsOnlyTenantAlerts(): void
    {
        $organizationA = $this->createOrganization('Org A');
        $organizationB = $this->createOrganization('Org B');
        $userA = $this->createUser($organizationA, 'alerts-a@test.local');
        $this->createUser($organizationB, 'alerts-b@test.local');

        $alertA = (new Alert())
            ->setTenantId($organizationA->getId())
            ->setType('sensor_threshold')
            ->setSeverity('warning')
            ->setTitle('Salle A')
            ->setMessage('Alerte A');
        $alertB = (new Alert())
            ->setTenantId($organizationB->getId())
            ->setType('sensor_threshold')
            ->setSeverity('critical')
            ->setTitle('Salle B')
            ->setMessage('Alerte B');

        $this->entityManager->persist($alertA);
        $this->entityManager->persist($alertB);
        $this->entityManager->flush();

        $this->authorizeClient($userA);
        $this->client->request('GET', '/api/alerts');

        $this->assertStatusCode(Response::HTTP_OK);

        $payload = json_decode($this->client->getResponse()->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        $members = $payload['hydra:member'] ?? $payload['member'] ?? (array_is_list($payload) ? $payload : []);
        self::assertCount(1, $members);
        self::assertSame('Alerte A', $members[0]['message']);
    }

    public function testAcknowledgeMarksAlertAsAcknowledged(): void
    {
        $organization = $this->createOrganization('Org Alerts');
        $organization->setLicenseStatus(LicenseStatus::ACTIVE);
        $user = $this->createUser($organization, 'alerts@test.local');

        $alert = (new Alert())
            ->setTenantId($organization->getId())
            ->setType('sensor_threshold')
            ->setSeverity('warning')
            ->setTitle('Salle')
            ->setMessage('Alerte');

        $this->entityManager->persist($alert);
        $this->entityManager->flush();

        $this->authorizeClient($user);
        $this->client->request('POST', sprintf('/api/alerts/%s/acknowledge', $alert->getId()), [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertStatusCode(Response::HTTP_OK);

        $this->entityManager->refresh($alert);
        self::assertNotNull($alert->getAcknowledgedAt());
    }

    public function testAcknowledgeIsForbiddenWhenTenantLicenseIsPending(): void
    {
        $organization = $this->createOrganization('Org Alerts Pending');
        $user = $this->createUser($organization, 'alerts-pending@test.local');

        $alert = (new Alert())
            ->setTenantId($organization->getId())
            ->setType('sensor_threshold')
            ->setSeverity('warning')
            ->setTitle('Salle')
            ->setMessage('Alerte');

        $this->entityManager->persist($alert);
        $this->entityManager->flush();

        $this->authorizeClient($user);
        $this->client->request('POST', sprintf('/api/alerts/%s/acknowledge', $alert->getId()), [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertStatusCode(Response::HTTP_FORBIDDEN);
    }
}
