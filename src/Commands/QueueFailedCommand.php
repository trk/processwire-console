<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use PDO;

final class QueueFailedCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('queue:failed')
            ->setDescription('List all failed queue jobs.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $db = \ProcessWire\wire('database');

        try {
            $stmt = $db->query("SELECT id, queue, payload, failed_at FROM failed_jobs ORDER BY id DESC LIMIT 50");
            $failedJobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($failedJobs)) {
                \Laravel\Prompts\info('No failed jobs found.');
                return Command::SUCCESS;
            }

            $rows = [];
            foreach ($failedJobs as $job) {
                // Try decoding the payload to show the class name
                $payload = json_decode($job['payload'], true);
                $class = $payload['class'] ?? 'UnknownClass';
                
                $rows[] = [
                    $job['id'],
                    $job['queue'],
                    $class,
                    $job['failed_at']
                ];
            }

            \Laravel\Prompts\table(
                ['ID', 'Queue', 'Class', 'Failed At'],
                $rows
            );

            return Command::SUCCESS;

        } catch (\PDOException $e) {
            // Table might not exist yet
            if (str_contains($e->getMessage(), "Table") && str_contains($e->getMessage(), "doesn't exist")) {
                \Laravel\Prompts\info('The failed_jobs table does not exist. Run `wire queue:table` to create it.');
                return Command::SUCCESS;
            }
            throw $e;
        }
    }
}
