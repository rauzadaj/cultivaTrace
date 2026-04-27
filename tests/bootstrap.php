<?php

use Symfony\Component\Dotenv\Dotenv;
use App\Security\JwtKeyBootstrapper;

require dirname(__DIR__).'/vendor/autoload.php';

if (($opensslConfig = getenv('OPENSSL_CONF')) === false || !is_file($opensslConfig)) {
    $fallbackOpenSslConfig = dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'extras' . DIRECTORY_SEPARATOR . 'ssl' . DIRECTORY_SEPARATOR . 'openssl.cnf';

    if (is_file($fallbackOpenSslConfig)) {
        putenv(sprintf('OPENSSL_CONF=%s', $fallbackOpenSslConfig));
        $_SERVER['OPENSSL_CONF'] = $fallbackOpenSslConfig;
        $_ENV['OPENSSL_CONF'] = $fallbackOpenSslConfig;
    }
}

if (method_exists(Dotenv::class, 'bootEnv')) {
    $projectDir = dirname(__DIR__);
    $dotenvPath = is_file($projectDir.'/.env') ? $projectDir.'/.env' : $projectDir.'/.env.test';

    (new Dotenv())->bootEnv($dotenvPath, 'test');
}

(new JwtKeyBootstrapper(dirname(__DIR__)))->ensure(
    (string) ($_SERVER['JWT_SECRET_KEY'] ?? getenv('JWT_SECRET_KEY') ?: '%kernel.project_dir%/var/jwt/private.pem'),
    (string) ($_SERVER['JWT_PUBLIC_KEY'] ?? getenv('JWT_PUBLIC_KEY') ?: '%kernel.project_dir%/var/jwt/public.pem'),
    (string) ($_SERVER['JWT_PASSPHRASE'] ?? getenv('JWT_PASSPHRASE') ?: 'change-me-in-local-and-prod'),
    (string) ($_SERVER['APP_ENV'] ?? getenv('APP_ENV') ?: 'test'),
    ($privateKeyBase64 = getenv('JWT_SECRET_KEY_BASE64')) !== false ? $privateKeyBase64 : null,
    ($publicKeyBase64 = getenv('JWT_PUBLIC_KEY_BASE64')) !== false ? $publicKeyBase64 : null,
);

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}
