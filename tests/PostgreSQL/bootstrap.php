<?php

declare(strict_types=1);

$database = getenv('PLANT_EVENT_TEST_DATABASE');
if (!$database || !preg_match('/^cultivatrace_it_[a-f0-9]{16}_test$/D', $database)) {
    throw new RuntimeException('Run this suite via bin/test-postgresql.php; never against an existing database.');
}
require dirname(__DIR__).'/bootstrap.php';
