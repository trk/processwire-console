<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Queue\QueueManager;
use Totoglu\Console\Traits\InteractWithProcessWire;

final class QueueWorkCommand extends Command
{
	use InteractWithProcessWire;

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
			->addOption('memory', null, InputOption::VALUE_REQUIRED, 'The memory limit in megabytes', 128)
			->addOption('once', null, InputOption::VALUE_NONE, 'Only process the next job on the queue')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$queueName = (string) $input->getOption('queue');
		$sleep = (int) $input->getOption('sleep');
		$maxTries = (int) $input->getOption('tries');
		$memoryLimit = (int) $input->getOption('memory') * 1024 * 1024;
		$once = (bool) $input->getOption('once');
		$asJson = (bool) $input->getOption('json');

		$manager = new QueueManager();
		$manager->ensureTables();
		self::$pendingManager = $manager;

		$this->registerSignalHandlers($asJson);

		if (!$asJson) {
			\Laravel\Prompts\info("Queue worker started for queue: [{$queueName}] (memory limit: {$memoryLimit} bytes)");
			\Laravel\Prompts\note('Press Ctrl+C to stop. Reserved jobs will be released gracefully.');
		}

		$processed = 0;
		$failed = 0;

		while (true) {
			if (self::$shouldQuit) {
				$this->releasePendingJob($asJson);
				if ($asJson) {
					return $this->outputJsonSuccess($output, ['queue' => $queueName, 'processed' => $processed, 'failed' => $failed, 'reason' => 'signal']);
				}
				\Laravel\Prompts\note('Worker stopped gracefully.');
				return Command::SUCCESS;
			}

			if ($this->memoryExceeded($memoryLimit)) {
				$this->releasePendingJob($asJson);
				if ($asJson) {
					return $this->outputJsonSuccess($output, ['queue' => $queueName, 'processed' => $processed, 'failed' => $failed, 'reason' => 'memory_limit']);
				}
				\Laravel\Prompts\note('Memory limit reached. Worker stopping to avoid leaks.');
				return Command::SUCCESS;
			}

			try {
				$job = $manager->pop($queueName);
				self::$pendingJob = $job;

				if ($job === null) {
					if ($once) {
						if ($asJson) {
							return $this->outputJsonSuccess($output, ['queue' => $queueName, 'processed' => $processed, 'failed' => $failed]);
						}
						return Command::SUCCESS;
					}
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

				if (!$asJson) {
					\Laravel\Prompts\info("Processing: {$className} (Job #{$job['id']})");
				}

				$startTime = microtime(true);

				$instance = $manager->resolveClass($className);
				$instance->handle($data);

				$manager->deleteJob((int) $job['id']);
				self::$pendingJob = null;
				$processed++;

				$duration = number_format((microtime(true) - $startTime) * 1000, 2);
				if (!$asJson) {
					\Laravel\Prompts\note("Processed: {$className} ({$duration}ms)");
				}

				if ($once) {
					if ($asJson) {
						return $this->outputJsonSuccess($output, ['queue' => $queueName, 'processed' => $processed, 'failed' => $failed, 'lastJob' => $className]);
					}
					return Command::SUCCESS;
				}
			} catch (\Throwable $e) {
				if (isset($job) && $job !== null) {
					$currentAttempts = (int) $job['attempts'];

					if ($currentAttempts >= $maxTries) {
						if (!$asJson) {
							\Laravel\Prompts\error("Failed: {$className} (Attempts: {$currentAttempts}) -> Moving to failed_jobs");
						}
						$manager->markFailed($job, $e);
						$failed++;
					} else {
						if (!$asJson) {
							\Laravel\Prompts\warning("Exception running {$className} (Attempt {$currentAttempts} of {$maxTries}): " . $e->getMessage());
						}
						$manager->releaseJob((int) $job['id'], 0);
					}
					self::$pendingJob = null;
					if ($once) {
						if ($asJson) {
							return $this->outputJsonError($output, 'JOB_FAILED', $e->getMessage());
						}
						return Command::FAILURE;
					}
				} else {
					if ($asJson && $once) {
						return $this->outputJsonError($output, 'WORKER_ERROR', $e->getMessage());
					}
					if (!$asJson) {
						\Laravel\Prompts\error('Worker Error: ' . $e->getMessage());
					}
					sleep($sleep);
				}
			}
		}
	}

	private function registerSignalHandlers(bool $asJson): void
	{
		if (!function_exists('pcntl_signal')) {
			if (!$asJson) {
				\Laravel\Prompts\warning('pcntl extension not available. Signals will not be handled gracefully (reserved jobs may get stuck).');
			}
			return;
		}

		pcntl_async_signals(true);

		$handler = function (int $signal) use ($asJson): void {
			self::$shouldQuit = true;
			if (!$asJson) {
				\Laravel\Prompts\note('Signal received, stopping after current job...');
			}
		};

		pcntl_signal(SIGINT, $handler);
		pcntl_signal(SIGTERM, $handler);
	}

	private function memoryExceeded(int $memoryLimit): bool
	{
		return memory_get_usage(true) > $memoryLimit;
	}

	private function releasePendingJob(bool $asJson): void
	{
		if (self::$pendingJob !== null && self::$pendingManager !== null) {
			try {
				$jobId = (int) self::$pendingJob['id'];
				self::$pendingManager->releaseJob($jobId, 5);
				if (!$asJson) {
					\Laravel\Prompts\note("Released pending job #{$jobId} back to the queue.");
				}
			} catch (\Throwable) {
				// Ignore errors during graceful shutdown release
			}
			self::$pendingJob = null;
		}
	}
}
