<?php

declare(strict_types=1);

// Boots the Symfony kernel and returns the Doctrine ObjectManager so PHPStan's
// Doctrine extension can resolve entity mappings (repository return types,
// query result shapes, etc.). Used by phpstan.dist.neon.

use App\Kernel;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

$projectDir = dirname(__DIR__);
$envPath = is_file($projectDir . '/.env') ? $projectDir . '/.env' : $projectDir . '/.env.test';
(new Dotenv())->bootEnv($envPath, 'test');

$kernel = new Kernel($_SERVER['APP_ENV'] ?? 'test', (bool) ($_SERVER['APP_DEBUG'] ?? false));
$kernel->boot();

/** @var ManagerRegistry $registry */
$registry = $kernel->getContainer()->get('doctrine');

return $registry->getManager();
