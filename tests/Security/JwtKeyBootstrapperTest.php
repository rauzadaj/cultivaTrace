<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Security\JwtKeyBootstrapper;
use PHPUnit\Framework\TestCase;

final class JwtKeyBootstrapperTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        $this->workspace = sys_get_temp_dir() . '/jwt-bootstrapper-' . bin2hex(random_bytes(6));
        mkdir($this->workspace, 0700, true);
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->workspace)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->workspace, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
                continue;
            }

            unlink($file->getPathname());
        }

        rmdir($this->workspace);
    }

    public function testItGeneratesKeysForTestEnvironment(): void
    {
        $bootstrapper = new JwtKeyBootstrapper($this->workspace);

        $bootstrapper->ensure(
            '%kernel.project_dir%/var/jwt/private.pem',
            '%kernel.project_dir%/var/jwt/public.pem',
            'test-passphrase',
            'test',
        );

        self::assertFileExists($this->workspace . '/var/jwt/private.pem');
        self::assertFileExists($this->workspace . '/var/jwt/public.pem');
        self::assertStringContainsString('BEGIN ENCRYPTED PRIVATE KEY', (string) file_get_contents($this->workspace . '/var/jwt/private.pem'));
        self::assertStringContainsString('BEGIN PUBLIC KEY', (string) file_get_contents($this->workspace . '/var/jwt/public.pem'));
    }

    public function testItFailsFastInProdWithoutKeys(): void
    {
        $bootstrapper = new JwtKeyBootstrapper($this->workspace);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('JWT key files are missing');

        $bootstrapper->ensure(
            '%kernel.project_dir%/var/jwt/private.pem',
            '%kernel.project_dir%/var/jwt/public.pem',
            'prod-passphrase',
            'prod',
        );
    }

    public function testItWritesBase64KeysWhenProvided(): void
    {
        $bootstrapper = new JwtKeyBootstrapper($this->workspace);

        $bootstrapper->ensure(
            '%kernel.project_dir%/var/jwt/private.pem',
            '%kernel.project_dir%/var/jwt/public.pem',
            'ignored',
            'prod',
            base64_encode("PRIVATE\n"),
            base64_encode("PUBLIC\n"),
        );

        self::assertSame("PRIVATE\n", file_get_contents($this->workspace . '/var/jwt/private.pem'));
        self::assertSame("PUBLIC\n", file_get_contents($this->workspace . '/var/jwt/public.pem'));
    }

    public function testItResolvesWindowsAbsolutePathsWithoutPrefixingProjectDir(): void
    {
        $windowsProjectDir = 'C:\\workspace\\cultivatrace';
        $bootstrapper = new JwtKeyBootstrapper($windowsProjectDir);

        $reflection = new \ReflectionClass($bootstrapper);
        $method = $reflection->getMethod('resolvePath');

        self::assertSame(
            'C:\\workspace\\cultivatrace\\var\\jwt\\private.pem',
            $method->invoke($bootstrapper, '%kernel.project_dir%\\var\\jwt\\private.pem'),
        );
    }
}
