<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\OutboxDispatcher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:outbox:dispatch', description: 'Deliver queued email, Mercure and billing effects.')]
final class DispatchOutboxCommand extends Command
{
    public function __construct(private readonly OutboxDispatcher $outbox)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum effects to process.', 100);
        $this->addOption('watch', null, InputOption::VALUE_NONE, 'Keep polling for new effects.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = filter_var($input->getOption('limit'), FILTER_VALIDATE_INT);
        if ($limit === false || $limit < 1 || $limit > 1000) {
            throw new \InvalidArgumentException('The limit must be between 1 and 1000.');
        }

        $watch = (bool) $input->getOption('watch');
        do {
            $result = $this->outbox->dispatchBatch($limit);
            if (!$watch || $result['delivered'] > 0 || $result['failed'] > 0) {
                $output->writeln(sprintf('Delivered: %d; failed: %d', $result['delivered'], $result['failed']));
            }
            if ($watch) {
                usleep(1_000_000);
            }
        } while ($watch);

        return $result['failed'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
