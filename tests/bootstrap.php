<?php

use Symfony\Component\Dotenv\Dotenv;
use App\Security\JwtKeyBootstrapper;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
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
