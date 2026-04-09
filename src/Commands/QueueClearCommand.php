<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class QueueClearCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('queue:clear')
            ->setDescription('Clear all failed jobs from the failed_jobs database table.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!\Laravel\Prompts\confirm('Are you sure you want to delete all failed jobs? This action cannot be undone.', default: false)) {
            \Laravel\Prompts\note('Action canceled.');
            return Command::SUCCESS;
        }

        $db = \ProcessWire\wire('database');

        try {
            $db->exec("DELETE FROM failed_jobs");
            $db->exec("ALTER TABLE failed_jobs AUTO_INCREMENT = 1"); // Reset index safely for MySQL

            \Laravel\Prompts\info('Failed jobs have been successfully cleared.');
            return Command::SUCCESS;
        } catch (\PDOException $e) {
            \Laravel\Prompts\error('Failed to clear jobs: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
