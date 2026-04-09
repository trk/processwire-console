<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use PDO;

final class QueueRetryCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('queue:retry')
            ->setDescription('Retry a failed queue job.')
            ->addArgument('id', InputArgument::REQUIRED, 'The ID of the failed job');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getArgument('id');
        $db = \ProcessWire\wire('database');

        if ($id === 'all') {
            // Re-queue all 
            $this->retryAll($db);
            return Command::SUCCESS;
        }

        $jobId = (int)$id;

        try {
            $stmt = $db->prepare("SELECT * FROM failed_jobs WHERE id = :id");
            $stmt->execute([':id' => $jobId]);
            $job = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$job) {
                \Laravel\Prompts\error("Failed job with ID [{$jobId}] not found.");
                return Command::FAILURE;
            }

            $this->requeueJob($db, $job);
            \Laravel\Prompts\info("Job [{$jobId}] has been pushed back onto the queue.");
            return Command::SUCCESS;

        } catch (\Exception $e) {
            \Laravel\Prompts\error("Error retrying job: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function retryAll(PDO $db): void
    {
        $stmt = $db->query("SELECT * FROM failed_jobs");
        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($jobs)) {
            \Laravel\Prompts\info('No failed jobs found.');
            return;
        }

        $count = 0;
        foreach ($jobs as $job) {
            $this->requeueJob($db, $job);
            $count++;
        }

        \Laravel\Prompts\info("Successfully re-queued {$count} job(s).");
    }

    private function requeueJob(PDO $db, array $job): void
    {
        // 1. Insert back into queue_jobs
        $insert = $db->prepare("
            INSERT INTO queue_jobs (queue, payload, attempts, available_at, created_at)
            VALUES (:queue, :payload, 0, :available_at, :created_at)
        ");
        $insert->execute([
            ':queue' => $job['queue'],
            ':payload' => $job['payload'],
            ':available_at' => time(),
            ':created_at' => time() // Reset created_at so it doesn't look ancient
        ]);

        // 2. Delete from failed_jobs
        $delete = $db->prepare("DELETE FROM failed_jobs WHERE id = :id");
        $delete->execute([':id' => $job['id']]);
    }
}
