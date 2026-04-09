<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Queue\QueueManager;

final class QueueWorkCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('queue:work')
            ->setDescription('Start processing jobs on the queue as a daemon.')
            ->addOption('queue', null, InputOption::VALUE_REQUIRED, 'The queue to listen on', 'default')
            ->addOption('sleep', null, InputOption::VALUE_REQUIRED, 'Number of seconds to sleep when no job is available', 3)
            ->addOption('tries', null, InputOption::VALUE_REQUIRED, 'Number of times to attempt a job before logging it failed', 3);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $queueName = (string)$input->getOption('queue');
        $sleep = (int)$input->getOption('sleep');
        $maxTries = (int)$input->getOption('tries');

        $manager = new QueueManager();
        $manager->ensureTables();

        \Laravel\Prompts\info("Queue worker started for queue: [{$queueName}]");
        \Laravel\Prompts\note('Press Ctrl+C to stop.');

        while (true) {
            try {
                $job = $manager->pop($queueName);

                if ($job === null) {
                    sleep($sleep);
                    continue;
                }

                $payload = json_decode($job['payload'], true);
                $className = $payload['class'] ?? null;
                $data = $payload['data'] ?? [];

                if (!$className) {
                    throw new \Exception('Invalid job payload: Missing class definition.');
                }

                \Laravel\Prompts\info("Processing: {$className} (Job #{$job['id']})");
                
                $startTime = microtime(true);
                
                // Resolve and Execute
                $instance = $manager->resolveClass($className);
                $instance->handle($data);
                
                // Success
                $manager->deleteJob((int)$job['id']);
                
                $duration = number_format((microtime(true) - $startTime) * 1000, 2);
                \Laravel\Prompts\note("Processed: {$className} ({$duration}ms)");

            } catch (\Throwable $e) {
                if (isset($job)) {
                    $currentAttempts = ((int)$job['attempts']); // already Incremented in pop()
                    
                    if ($currentAttempts >= $maxTries) {
                        \Laravel\Prompts\error("Failed: {$className} (Attempts: {$currentAttempts}) -> Moving to failed_jobs");
                        $manager->markFailed($job, $e);
                    } else {
                        \Laravel\Prompts\warning("Exception running {$className} (Attempt {$currentAttempts} of {$maxTries}): " . $e->getMessage());
                        // Release back to queue immediately or with delay
                        $manager->releaseJob((int)$job['id'], 0);
                    }
                } else {
                    // System level exception (e.g. database connection lost)
                    \Laravel\Prompts\error("Worker Error: " . $e->getMessage());
                    sleep($sleep);
                }
            }
        }

        return Command::SUCCESS;
    }
}
