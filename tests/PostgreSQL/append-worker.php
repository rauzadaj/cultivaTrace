<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';

$kernel = new App\Kernel('test', true);
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();
$db = $em->getConnection();
if ($db->fetchOne('SELECT current_database()') !== getenv('PLANT_EVENT_TEST_DATABASE')) {
    throw new RuntimeException('Unexpected worker database.');
}
$db->executeStatement("SET lock_timeout = '15s'");
$plant = $em->find(App\Entity\Plant::class, $argv[1]);
$user = $em->find(App\Entity\User::class, $argv[2]);
$repo = $em->getRepository(App\Entity\PlantEvent::class);
$db->beginTransaction();
echo json_encode(['pid' => (int) $db->fetchOne('SELECT pg_backend_pid()'), 'read_previous' => $repo->findLastForPlant($plant->getId())?->getHashSelf() ?? str_repeat('0', 64)], JSON_THROW_ON_ERROR)."\n";
flush();
if (trim((string) fgets(STDIN)) !== 'append') {
    throw new RuntimeException('The parent did not release the append barrier.');
}
$event = $repo->appendEvent($plant, 'note', $user, ['message' => 'B', 'unit' => 'g', 'metadata' => ['source' => 'concurrent', 'operator' => 'B']]);
$db->commit();
echo json_encode(['previous' => $event->getHashPrevious(), 'self' => $event->getHashSelf()], JSON_THROW_ON_ERROR)."\n";
$em->close();
$kernel->shutdown();
