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
    private static bool $shouldQuit = false;
    private static ?array $pendingJob = null;
    private static ?QueueManager $pendingManager = null;

    protected function configure(): void
    {
        $this
            ->setName('queue:work')
            ->setDescription('Start processing jobs on the queue as a daemon.')
            ->addOption('queue', null, InputOption::VALUE_REQUIRED, 'The queue to listen on', 'default')
            ->addOption('sleep', null, InputOption::VALUE_REQUIRED, 'Number of seconds to sleep when no job is available', 3)
            ->addOption('tries', null, InputOption::VALUE_REQUIRED, 'Number of times to attempt a job before logging it failed', 3)
            ->addOption('memory', null, InputOption::VALUE_REQUIRED, 'The memory limit in megabytes', 128);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $queueName = (string)$input->getOption('queue');
        $sleep = (int)$input->getOption('sleep');
        $maxTries = (int)$input->getOption('tries');
        $memoryLimit = (int)$input->getOption('memory') * 1024 * 1024;

        $manager = new QueueManager();
        $manager->ensureTables();
        self::$pendingManager = $manager;

        $this->registerSignalHandlers();

        \Laravel\Prompts\info("Queue worker started for queue: [{$queueName}] (memory limit: {$memoryLimit} bytes)");
        \Laravel\Prompts\note('Press Ctrl+C to stop. Reserved jobs will be released gracefully.');

        while (true) {
            if (self::$shouldQuit) {
                $this->releasePendingJob();
                \Laravel\Prompts\note('Worker stopped gracefully.');
                return Command::SUCCESS;
            }

            if ($this->memoryExceeded($memoryLimit)) {
                $this->releasePendingJob();
                \Laravel\Prompts\note('Memory limit reached. Worker stopping to avoid leaks.');
                return Command::SUCCESS;
            }

            try {
                $job = $manager->pop($queueName);
                self::$pendingJob = $job;

                if ($job === null) {
                    sleep($sleep);
                    continue;
                }

                $payload = json_decode($job['payload'] ?? '', true);
                if (!is_array($payload)) {
                    $payload = [];
                }
                $className = $payload['class'] ?? 'UnknownJob';
                $data = $payload['data'] ?? [];

                if (!isset($payload['class'])) {
                    throw new \Exception('Invalid job payload: Missing class definition.');
                }

                \Laravel\Prompts\info("Processing: {$className} (Job #{$job['id']})");
                
                $startTime = microtime(true);
                
                // Resolve and Execute
                $instance = $manager->resolveClass($className);
                $instance->handle($data);
                
                // Success
                $manager->deleteJob((int)$job['id']);
                self::$pendingJob = null;
                
                $duration = number_format((microtime(true) - $startTime) * 1000, 2);
                \Laravel\Prompts\note("Processed: {$className} ({$duration}ms)");

            } catch (\Throwable $e) {
                if (isset($job) && $job !== null) {
                    $currentAttempts = ((int)$job['attempts']); // already Incremented in pop()
                    
                    if ($currentAttempts >= $maxTries) {
                        \Laravel\Prompts\error("Failed: {$className} (Attempts: {$currentAttempts}) -> Moving to failed_jobs");
                        $manager->markFailed($job, $e);
                    } else {
                        \Laravel\Prompts\warning("Exception running {$className} (Attempt {$currentAttempts} of {$maxTries}): " . $e->getMessage());
                        // Release back to queue immediately or with delay
                        $manager->releaseJob((int)$job['id'], 0);
                    }
                    self::$pendingJob = null;
                } else {
                    // System level exception (e.g. database connection lost)
                    \Laravel\Prompts\error("Worker Error: " . $e->getMessage());
                    sleep($sleep);
                }
            }
        }

        return Command::SUCCESS;
    }

    private function registerSignalHandlers(): void
    {
        if (!function_exists('pcntl_signal')) {
            \Laravel\Prompts\warning('pcntl extension not available. Signals will not be handled gracefully (reserved jobs may get stuck).');
            return;
        }

        pcntl_async_signals(true);

        $handler = function (int $signal): void {
            self::$shouldQuit = true;
            \Laravel\Prompts\note('Signal received, stopping after current job...');
        };

        pcntl_signal(SIGINT, $handler);
        pcntl_signal(SIGTERM, $handler);
    }

    private function memoryExceeded(int $memoryLimit): bool
    {
        return memory_get_usage(true) > $memoryLimit;
    }

    private function releasePendingJob(): void
    {
        if (self::$pendingJob !== null && self::$pendingManager !== null) {
            try {
                $jobId = (int)self::$pendingJob['id'];
                self::$pendingManager->releaseJob($jobId, 5);
                \Laravel\Prompts\note("Released pending job #{$jobId} back to the queue.");
            } catch (\Throwable) {
                // Ignore errors during graceful shutdown release
            }
            self::$pendingJob = null;
        }
    }
}
