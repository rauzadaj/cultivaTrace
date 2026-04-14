#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Security\JwtKeyBootstrapper;

require dirname(__DIR__) . '/vendor/autoload.php';

$projectDir = dirname(__DIR__);
$appEnv = (string) ($_SERVER['APP_ENV'] ?? getenv('APP_ENV') ?: 'dev');
$privateKeyPath = (string) ($_SERVER['JWT_SECRET_KEY'] ?? getenv('JWT_SECRET_KEY') ?: '%kernel.project_dir%/var/jwt/private.pem');
$publicKeyPath = (string) ($_SERVER['JWT_PUBLIC_KEY'] ?? getenv('JWT_PUBLIC_KEY') ?: '%kernel.project_dir%/var/jwt/public.pem');
$passphrase = (string) ($_SERVER['JWT_PASSPHRASE'] ?? getenv('JWT_PASSPHRASE') ?: 'change-me-in-local-and-prod');
$privateKeyBase64 = getenv('JWT_SECRET_KEY_BASE64');
$publicKeyBase64 = getenv('JWT_PUBLIC_KEY_BASE64');

(new JwtKeyBootstrapper($projectDir))->ensure(
    $privateKeyPath,
    $publicKeyPath,
    $passphrase,
    $appEnv,
    $privateKeyBase64 !== false ? $privateKeyBase64 : null,
    $publicKeyBase64 !== false ? $publicKeyBase64 : null,
);

fwrite(STDOUT, "JWT key material is ready.\n");
