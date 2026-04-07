<?php

declare(strict_types=1);

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

    public function testUploadQueuesHealthCanadaLicenseForManualReviewByDefault(): void
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
        self::assertSame('pending', $payload['status']);
        self::assertStringContainsString('Vérification manuelle', $payload['message']);
    }

    public function testUploadQueuesInvalidMetrcLicenseForManualReviewWhenAutoVerificationFails(): void
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
        self::assertSame('pending', $payload['status']);
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

    public function testUploadRejectsUserWithoutWriteRole(): void
    {
        $organization = $this->createOrganization('Org KYB');
        $user = $this->createUser($organization, 'kyb-viewer@test.local', roles: []);
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

        $this->assertStatusCode(Response::HTTP_FORBIDDEN);
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

    public function testUploadTrimsWhitespaceAroundDevCtlsLicenseNumber(): void
    {
        $organization = $this->createOrganization('Org KYB CTLS Trim');
        $user = $this->createUser($organization, 'kyb-ctls-trim@test.local');
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
                'licenseNumber' => ' TEST-CTLS-DEMO-001 ',
                'licenseType' => ' ctls_dev ',
            ], JSON_THROW_ON_ERROR),
        );

        $this->assertStatusCode(Response::HTTP_CREATED);

        $payload = json_decode($this->client->getResponse()->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('active', $payload['status']);
    }

    public function testUploadAcceptsValidPdfDocument(): void
    {
        [$organization, $user] = $this->createKybFixture('kyb-valid-file@test.local');
        $filePath = tempnam(sys_get_temp_dir(), 'kyb_pdf_');
        self::assertNotFalse($filePath);
        file_put_contents($filePath, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");

        $this->authorizeClient($user);
        $this->client->request(
            'POST',
            '/api/kyb/upload',
            [
                'licenseNumber' => 'HC-LP-FILE-001',
                'licenseType' => 'health_canada',
            ],
            [
                'file' => new UploadedFile($filePath, 'license.pdf', 'application/pdf', null, true),
            ],
            [
                'HTTP_ACCEPT' => 'application/json',
            ],
        );

        $this->assertStatusCode(Response::HTTP_CREATED);

        /** @var LicenseDocument|null $license */
        $license = $this->entityManager->getRepository(LicenseDocument::class)
            ->findOneBy(['organization' => $organization], ['submittedAt' => 'DESC']);

        self::assertNotNull($license);
        self::assertNotNull($license->getFilePath());
        self::assertStringEndsWith('.pdf', $license->getFilePath());
    }

    public function testUploadRejectsFileWithInvalidDetectedMimeType(): void
    {
        [, $user] = $this->createKybFixture('kyb-invalid-file@test.local');
        $filePath = tempnam(sys_get_temp_dir(), 'kyb_bad_');
        self::assertNotFalse($filePath);
        file_put_contents($filePath, '<script>alert(1)</script>');

        $this->authorizeClient($user);
        $this->client->request(
            'POST',
            '/api/kyb/upload',
            [
                'licenseNumber' => 'HC-LP-INVALID-FILE',
                'licenseType' => 'health_canada',
            ],
            [
                'file' => new UploadedFile($filePath, 'license.png', 'image/png', null, true),
            ],
            [
                'HTTP_ACCEPT' => 'application/json',
            ],
        );

        $this->assertStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);

        $payload = json_decode($this->client->getResponse()->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Le fichier fourni est invalide.', $payload['error']);
    }

    public function testUploadRejectsFileAboveTenMegabytes(): void
    {
        [, $user] = $this->createKybFixture('kyb-oversize@test.local');
        $filePath = tempnam(sys_get_temp_dir(), 'kyb_big_');
        self::assertNotFalse($filePath);
        file_put_contents($filePath, "%PDF-1.4\n" . str_repeat('A', 10 * 1024 * 1024 + 1));

        $this->authorizeClient($user);
        $this->client->request(
            'POST',
            '/api/kyb/upload',
            [
                'licenseNumber' => 'HC-LP-BIG-FILE',
                'licenseType' => 'health_canada',
            ],
            [
                'file' => new UploadedFile($filePath, 'license.pdf', 'application/pdf', null, true),
            ],
            [
                'HTTP_ACCEPT' => 'application/json',
            ],
        );

        $this->assertStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);

        $payload = json_decode($this->client->getResponse()->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Le fichier fourni est invalide.', $payload['error']);
    }

    /**
     * @return array{0: Organization, 1: User}
     */
    private function createKybFixture(string $email): array
    {
        $organization = $this->createOrganization('Org KYB Upload');
        $user = $this->createUser($organization, $email);
        $this->entityManager->flush();

        return [$organization, $user];
    }
}
