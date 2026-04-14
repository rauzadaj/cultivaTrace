<?php

declare(strict_types=1);

namespace App\Security;

final readonly class JwtKeyBootstrapper
{
    public function __construct(
        private string $projectDir,
    ) {
    }

    public function ensure(
        string $privateKeyPath,
        string $publicKeyPath,
        string $passphrase,
        string $appEnv,
        ?string $privateKeyBase64 = null,
        ?string $publicKeyBase64 = null,
    ): void {
        $resolvedPrivateKeyPath = $this->resolvePath($privateKeyPath);
        $resolvedPublicKeyPath = $this->resolvePath($publicKeyPath);

        if (is_file($resolvedPrivateKeyPath) && is_file($resolvedPublicKeyPath)) {
            return;
        }

        $this->ensureDirectory(\dirname($resolvedPrivateKeyPath));
        $this->ensureDirectory(\dirname($resolvedPublicKeyPath));

        if ($privateKeyBase64 !== null || $publicKeyBase64 !== null) {
            if ($privateKeyBase64 === null || $publicKeyBase64 === null) {
                throw new \RuntimeException('Both JWT_SECRET_KEY_BASE64 and JWT_PUBLIC_KEY_BASE64 must be provided together.');
            }

            $this->writeDecodedKey($resolvedPrivateKeyPath, $privateKeyBase64, 0600, 'private');
            $this->writeDecodedKey($resolvedPublicKeyPath, $publicKeyBase64, 0644, 'public');

            return;
        }

        if (!\in_array($appEnv, ['dev', 'test'], true)) {
            throw new \RuntimeException('JWT key files are missing. Provide JWT_SECRET_KEY/JWT_PUBLIC_KEY files or inject JWT_SECRET_KEY_BASE64 and JWT_PUBLIC_KEY_BASE64 in production.');
        }

        $this->generateKeyPair($resolvedPrivateKeyPath, $resolvedPublicKeyPath, $passphrase);
    }

    private function resolvePath(string $path): string
    {
        $resolved = str_replace('%kernel.project_dir%', $this->projectDir, $path);

        if ($resolved === '') {
            return $this->projectDir . '/var/jwt/private.pem';
        }

        if ($resolved[0] === '/') {
            return $resolved;
        }

        return $this->projectDir . '/' . ltrim($resolved, '/');
    }

    private function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (!mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Unable to create JWT key directory "%s".', $directory));
        }
    }

    private function writeDecodedKey(string $path, string $encodedKey, int $mode, string $label): void
    {
        $decoded = base64_decode(trim($encodedKey), true);

        if ($decoded === false || trim($decoded) === '') {
            throw new \RuntimeException(sprintf('Unable to decode %s JWT key from base64.', $label));
        }

        if (file_put_contents($path, $decoded) === false) {
            throw new \RuntimeException(sprintf('Unable to write %s JWT key to "%s".', $label, $path));
        }

        @chmod($path, $mode);
    }

    private function generateKeyPair(string $privateKeyPath, string $publicKeyPath, string $passphrase): void
    {
        $resource = openssl_pkey_new([
            'private_key_bits' => 4096,
            'private_key_type' => \OPENSSL_KEYTYPE_RSA,
        ]);

        if ($resource === false) {
            throw new \RuntimeException('Unable to generate JWT private key pair.');
        }

        $privateKey = null;
        if (!openssl_pkey_export($resource, $privateKey, $passphrase)) {
            throw new \RuntimeException('Unable to export JWT private key.');
        }

        $details = openssl_pkey_get_details($resource);
        $publicKey = \is_array($details) ? ($details['key'] ?? null) : null;

        if (!\is_string($publicKey) || trim($publicKey) === '') {
            throw new \RuntimeException('Unable to derive JWT public key.');
        }

        if (file_put_contents($privateKeyPath, $privateKey) === false) {
            throw new \RuntimeException(sprintf('Unable to write JWT private key to "%s".', $privateKeyPath));
        }

        if (file_put_contents($publicKeyPath, $publicKey) === false) {
            throw new \RuntimeException(sprintf('Unable to write JWT public key to "%s".', $publicKeyPath));
        }

        @chmod($privateKeyPath, 0600);
        @chmod($publicKeyPath, 0644);
    }
}
