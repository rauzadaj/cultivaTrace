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
$connectionParams = (new DsnParser(['postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql']))->parse($testUrl);
$connectionParams['dbname'] = $databaseName; // Direct DBAL connections do not apply Doctrine's test suffix.
$testConnection = null;
$status = 1;
$created = false;
try {
    $admin->executeStatement('CREATE DATABASE '.$admin->quoteIdentifier($databaseName));
    $created = true;
    echo "PostgreSQL $version; created $databaseName\n";

    // Keep the fresh-install path covered independently from the upgrade path below.
    runMigration($env, null);
    verifyFreshSchema(DriverManager::getConnection($connectionParams));

    // Recreate the disposable database so the second scenario starts at a real prior release.
    $admin->executeStatement('DROP DATABASE '.$admin->quoteIdentifier($databaseName).' WITH (FORCE)');
    $admin->executeStatement('CREATE DATABASE '.$admin->quoteIdentifier($databaseName));

    // Exercise a realistic in-place upgrade from the last schema before effect_outbox.
    runMigration($env, 'DoctrineMigrations\\Version20260602000000');

    $testConnection = DriverManager::getConnection($connectionParams);
    seedUpgradeFixture($testConnection);

    runMigration($env, null);
    verifyUpgradeFixture($testConnection);

    foreach ([
        [PHP_BINARY, 'bin/phpunit', '-c', 'phpunit.postgresql.xml.dist', ...array_slice($argv, 1)],
    ] as $command) {
        $process = new Process($command, dirname(__DIR__), $env, timeout: 180);
        $status = $process->run(static function (string $type, string $output): void { echo $output; });
        if ($status !== 0) {
            break;
        }
    }
} finally {
    $testConnection?->close();
    if ($created) {
        // Only a name generated above can reach DROP; never accepts an existing database name.
        $admin->executeStatement('DROP DATABASE '.$admin->quoteIdentifier($databaseName).' WITH (FORCE)');
        echo "Dropped $databaseName\n";
    }
    $admin->close();
}
exit($status);

function seedUpgradeFixture(\Doctrine\DBAL\Connection $connection): void
{
    $connection->executeStatement("INSERT INTO organization (id, name, country, plan, license_status, created_at) VALUES ('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'Upgrade fixture', 'CA', 'growth', 'active', NOW())");
    $connection->executeStatement("INSERT INTO \"user\" (id, email, roles, password, organization_id, account_status, role) VALUES (918273, 'migration-fixture@example.test', '[]', 'not-a-real-password', 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'active', 'ROLE_ORG_ADMIN')");
    $connection->executeStatement("INSERT INTO farm (id, tenant_id, organization_id, name) VALUES ('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'Upgrade farm')");
    $connection->executeStatement("INSERT INTO room (id, tenant_id, farm_id, name, type, capacity_max) VALUES ('cccccccc-cccc-4ccc-8ccc-cccccccccccc', 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'Upgrade room', 'veg', 20)");
    $connection->executeStatement("INSERT INTO plant (id, tenant_id, rfid_tag, stage, status, germinated_at, created_at, room_id, created_by_id) VALUES ('dddddddd-dddd-4ddd-8ddd-dddddddddddd', 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'UPGRADE-001', 'germination', 'active', CURRENT_DATE, NOW(), 'cccccccc-cccc-4ccc-8ccc-cccccccccccc', 918273)");
    $connection->executeStatement("INSERT INTO plant_event (id, tenant_id, event_type, payload, occurred_at, hash_previous, hash_self, ip_address, plant_id, user_id) VALUES ('eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee', 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'note', '{\"migrationFixture\":true}', NOW(), REPEAT('0', 64), REPEAT('1', 64), '127.0.0.1', 'dddddddd-dddd-4ddd-8ddd-dddddddddddd', 918273)");
    $connection->executeStatement('SELECT setval(pg_get_serial_sequence(\'"user"\', \'id\'), GREATEST((SELECT MAX(id) FROM "user"), 1))');
    echo "Seeded representative tenant, farm, room, plant and append-only event rows before upgrade.\n";
}

/** @param array<string, string> $env */
function runMigration(array $env, ?string $target): void
{
    $command = [PHP_BINARY, 'bin/console', 'doctrine:migrations:migrate'];
    if ($target !== null) {
        $command[] = $target;
    }
    array_push($command, '--no-interaction', '--env=test');

    $process = new Process($command, dirname(__DIR__), $env, timeout: 180);
    $status = $process->run(static function (string $type, string $output): void { echo $output; });
    if ($status !== 0) {
        throw new RuntimeException(sprintf('Doctrine migration command failed with exit code %d.', $status));
    }
}

function verifyFreshSchema(\Doctrine\DBAL\Connection $connection): void
{
    try {
        if (!$connection->createSchemaManager()->tablesExist(['organization', 'plant_event', 'effect_outbox'])) {
            throw new RuntimeException('Fresh migration did not create the expected latest schema.');
        }
        if ((int) $connection->fetchOne("SELECT COUNT(*) FROM pg_trigger WHERE tgname = 'trg_plant_event_append_only' AND tgenabled = 'O'") !== 1) {
            throw new RuntimeException('Fresh migration did not install the PlantEvent append-only trigger.');
        }
        $latest = $connection->fetchOne('SELECT version FROM doctrine_migration_versions ORDER BY version DESC LIMIT 1');
        if ($latest !== 'DoctrineMigrations\\Version20261118000000') {
            throw new RuntimeException(sprintf('Fresh migration stopped at unexpected migration %s.', (string) $latest));
        }
        echo "Empty database migrated to the latest schema successfully.\n";
    } finally {
        $connection->close();
    }
}

function verifyUpgradeFixture(\Doctrine\DBAL\Connection $connection): void
{
    if ((int) $connection->fetchOne("SELECT COUNT(*) FROM plant_event WHERE id = 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee' AND tenant_id = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'") !== 1) {
        throw new RuntimeException('Upgrade did not preserve the representative PlantEvent row.');
    }

    $tenantTables = $connection->fetchFirstColumn("SELECT table_name FROM information_schema.columns WHERE table_schema = 'public' AND column_name = 'tenant_id' ORDER BY table_name");
    foreach ($tenantTables as $table) {
        if ((int) $connection->fetchOne('SELECT COUNT(*) FROM '.$connection->quoteIdentifier($table).' WHERE tenant_id IS NULL') !== 0) {
            throw new RuntimeException(sprintf('Upgrade left tenant_id NULL rows in %s.', $table));
        }
    }

    if ((int) $connection->fetchOne("SELECT COUNT(*) FROM pg_trigger WHERE tgname = 'trg_plant_event_append_only' AND tgenabled = 'O'") !== 1) {
        throw new RuntimeException('Upgrade did not preserve the PlantEvent append-only trigger.');
    }

    $connection->beginTransaction();
    try {
        $connection->executeStatement('SAVEPOINT append_only_check');
        try {
            $connection->executeStatement("UPDATE plant_event SET notes = 'should fail' WHERE id = 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee'");
            throw new RuntimeException('PlantEvent update unexpectedly succeeded after upgrade.');
        } catch (\Doctrine\DBAL\Exception\DriverException) {
            $connection->executeStatement('ROLLBACK TO SAVEPOINT append_only_check');
        }
        $connection->rollBack();
    } catch (\Throwable $exception) {
        if ($connection->isTransactionActive()) {
            $connection->rollBack();
        }
        throw $exception;
    }

    $latest = $connection->fetchOne('SELECT version FROM doctrine_migration_versions ORDER BY version DESC LIMIT 1');
    if ($latest !== 'DoctrineMigrations\\Version20261118000000') {
        throw new RuntimeException(sprintf('Upgrade stopped at unexpected migration %s.', (string) $latest));
    }
    echo "Upgrade preserved tenant data and append-only protections; all tenant_id values are non-null.\n";
}
