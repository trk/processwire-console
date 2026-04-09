<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Queue\QueueManager;

final class QueueTableCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('queue:table')
            ->setDescription('Create the queue_jobs and failed_jobs tables if they do not exist.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $manager = new QueueManager();
            $manager->ensureTables();
            
            \Laravel\Prompts\info('Queue tables [queue_jobs, failed_jobs] have been created or verified.');
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            \Laravel\Prompts\error('Failed to create queue tables: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
