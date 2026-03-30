<?php

namespace App\Tests\Controller;

use App\Entity\LicenseDocument;
use App\Entity\Organization;
use App\Entity\User;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

final class KybControllerTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetSchema([
            Organization::class,
            User::class,
            LicenseDocument::class,
        ]);
    }

    public function testUploadActivatesHealthCanadaLicenseInDev(): void
    {
        $organization = $this->createOrganization('Org KYB');
        $user = $this->createUser($organization, 'kyb-active@test.local');
        $this->entityManager->flush();

        $this->authorizeClient($user);
        $this->client->request(
            'POST',
            '/api/kyb/upload',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            json_encode([
                'licenseNumber' => 'HC-LP-12345',
                'licenseType' => 'health_canada',
            ], JSON_THROW_ON_ERROR),
        );

        $this->assertStatusCode(Response::HTTP_CREATED);

        $payload = json_decode($this->client->getResponse()->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('active', $payload['status']);
        self::assertStringContainsString('activ', $payload['message']);
    }

    public function testUploadRejectsInvalidLicenseInDev(): void
    {
        $organization = $this->createOrganization('Org KYB');
        $user = $this->createUser($organization, 'kyb-invalid@test.local');
        $this->entityManager->flush();

        $this->authorizeClient($user);
        $this->client->request(
            'POST',
            '/api/kyb/upload',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            json_encode([
                'licenseNumber' => 'INVALID-123',
                'licenseType' => 'metrc_usa',
            ], JSON_THROW_ON_ERROR),
        );

        $this->assertStatusCode(Response::HTTP_CREATED);

        $payload = json_decode($this->client->getResponse()->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('rejected', $payload['status']);
    }

    public function testGetStatusReturnsCurrentLicenseStatus(): void
    {
        $organization = $this->createOrganization('Org KYB');
        $user = $this->createUser($organization, 'kyb-status@test.local');
        $this->entityManager->flush();

        $this->authorizeClient($user);
        $this->client->request('GET', '/api/kyb/status');

        $this->assertStatusCode(Response::HTTP_OK);

        $payload = json_decode($this->client->getResponse()->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('pending', $payload['licenseStatus']);
        self::assertSame((string) $organization->getId(), $payload['organizationId']);
    }

    public function testUploadWithoutLicenseNumberReturns422(): void
    {
        $organization = $this->createOrganization('Org KYB');
        $user = $this->createUser($organization, 'kyb-missing@test.local');
        $this->entityManager->flush();

        $this->authorizeClient($user);
        $this->client->request(
            'POST',
            '/api/kyb/upload',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            json_encode([
                'licenseType' => 'health_canada',
            ], JSON_THROW_ON_ERROR),
        );

        $this->assertStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testUploadActivatesDevCtlsLicenseWithExplicitTestPrefix(): void
    {
        $organization = $this->createOrganization('Org KYB CTLS');
        $user = $this->createUser($organization, 'kyb-ctls@test.local');
        $this->entityManager->flush();

        $this->authorizeClient($user);
        $this->client->request(
            'POST',
            '/api/kyb/upload',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            json_encode([
                'licenseNumber' => 'TEST-CTLS-DEMO-001',
                'licenseType' => 'ctls_dev',
            ], JSON_THROW_ON_ERROR),
        );

        $this->assertStatusCode(Response::HTTP_CREATED);

        $payload = json_decode($this->client->getResponse()->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('active', $payload['status']);
    }

    public function testUploadRejectsUnsupportedFileType(): void
    {
        $organization = $this->createOrganization('Org KYB File Type');
        $user = $this->createUser($organization, 'kyb-filetype@test.local');
        $this->entityManager->flush();

        $tmpFile = tempnam(sys_get_temp_dir(), 'kyb_txt_');
        file_put_contents($tmpFile, 'plain text content');

        $uploadedFile = new UploadedFile(
            $tmpFile,
            'license.txt',
            'text/plain',
            null,
            true,
        );

        $this->authorizeClient($user);
        $this->client->request(
            'POST',
            '/api/kyb/upload',
            [
                'licenseNumber' => 'HC-LP-12345',
                'licenseType' => 'health_canada',
            ],
            [
                'file' => $uploadedFile,
            ],
            [
                'HTTP_ACCEPT' => 'application/json',
            ],
        );

        $this->assertStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertStringContainsString('Formats autorisés', $this->client->getResponse()->getContent() ?: '');
    }

    public function testUploadRejectsFileLargerThanTenMegabytes(): void
    {
        $organization = $this->createOrganization('Org KYB File Size');
        $user = $this->createUser($organization, 'kyb-filesize@test.local');
        $this->entityManager->flush();

        $tmpFile = tempnam(sys_get_temp_dir(), 'kyb_pdf_');
        file_put_contents($tmpFile, "%PDF-1.4\n" . str_repeat('A', 10 * 1024 * 1024 + 1));

        $uploadedFile = new UploadedFile(
            $tmpFile,
            'license.pdf',
            'application/pdf',
            null,
            true,
        );

        $this->authorizeClient($user);
        $this->client->request(
            'POST',
            '/api/kyb/upload',
            [
                'licenseNumber' => 'HC-LP-12345',
                'licenseType' => 'health_canada',
            ],
            [
                'file' => $uploadedFile,
            ],
            [
                'HTTP_ACCEPT' => 'application/json',
            ],
        );

        $this->assertStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertStringContainsString('10MB', $this->client->getResponse()->getContent() ?: '');
    }
}
