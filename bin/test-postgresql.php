<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Symfony\Component\Process\Process;

require dirname(__DIR__).'/vendor/autoload.php';

// Deliberately never use DATABASE_URL as an administrative connection.
$adminUrl = getenv('POSTGRES_TEST_ADMIN_URL');
if (!$adminUrl) {
    fwrite(STDERR, "Set POSTGRES_TEST_ADMIN_URL to a disposable PostgreSQL 16 server (see docs/plant-event-integration.md).\n");
    exit(2);
}
$params = (new DsnParser(['postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql']))->parse($adminUrl);
$admin = DriverManager::getConnection($params);
$version = (int) $admin->fetchOne('SHOW server_version_num');
if ($version < 160000 || $version >= 170000) {
    throw new RuntimeException('This suite requires real PostgreSQL 16; got '.$version);
}
$baseName = 'cultivatrace_it_'.bin2hex(random_bytes(8));
$databaseName = $baseName.'_test'; // config/packages/test/doctrine.yaml adds this suffix.
$parts = parse_url($adminUrl);
if ($parts === false || !isset($parts['host'], $parts['path']) || $parts['path'] === '/') {
    throw new RuntimeException('Use a PostgreSQL URL with an explicit host and administrative database, such as /postgres.');
}
$testUrl = preg_replace('~(/)[^/?]*(\?.*)?$~', '/'.$baseName.'?serverVersion=16&charset=utf8', $adminUrl);
if ($testUrl === null || $testUrl === $adminUrl) {
    throw new RuntimeException('Use an administrative URL including /postgres.');
}
$env = [
    'APP_ENV' => 'test', 'APP_DEBUG' => '1', 'DATABASE_URL' => $testUrl,
    'PLANT_EVENT_TEST_DATABASE' => $databaseName,
];
$status = 1;
$created = false;
try {
    $admin->executeStatement('CREATE DATABASE '.$admin->quoteIdentifier($databaseName));
    $created = true;
    echo "PostgreSQL $version; created $databaseName\n";
    foreach ([
        [PHP_BINARY, 'bin/console', 'doctrine:migrations:migrate', '--no-interaction', '--env=test'],
        [PHP_BINARY, 'bin/phpunit', '-c', 'phpunit.postgresql.xml.dist', ...array_slice($argv, 1)],
    ] as $command) {
        $process = new Process($command, dirname(__DIR__), $env, timeout: 180);
        $status = $process->run(static function (string $type, string $output): void { echo $output; });
        if ($status !== 0) {
            break;
        }
    }
} finally {
    if ($created) {
        // Only a name generated above can reach DROP; never accepts an existing database name.
        $admin->executeStatement('DROP DATABASE '.$admin->quoteIdentifier($databaseName).' WITH (FORCE)');
        echo "Dropped $databaseName\n";
    }
    $admin->close();
}
exit($status);
