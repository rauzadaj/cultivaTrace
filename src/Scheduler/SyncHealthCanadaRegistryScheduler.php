<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Command\SyncHealthCanadaRegistryCommand;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

/**
 * Runs app:sync-health-canada-registry every day at 06:00 UTC.
 * Keeps the local registry fresh for offline KYB lookups.
 */
#[AsCronTask('0 6 * * *', method: 'sync')]
class SyncHealthCanadaRegistryScheduler
{
    public function __construct(
        private readonly SyncHealthCanadaRegistryCommand $command,
        private readonly LoggerInterface $logger,
    ) {}

    public function sync(): void
    {
        $this->logger->info('[Scheduler] Starting Health Canada registry sync');

        $input  = new ArrayInput([]);
        $output = new NullOutput();

        $input->setInteractive(false);

        $code = $this->command->run($input, $output);

        if ($code !== 0) {
            $this->logger->error('[Scheduler] Health Canada sync failed', ['exit_code' => $code]);
        }
    }
}
