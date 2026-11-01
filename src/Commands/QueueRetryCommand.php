<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Queue\QueueManager;
use Totoglu\Console\Traits\InteractWithProcessWire;

final class QueueRetryCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('queue:retry')
			->setDescription('Retry a failed queue job.')
			->addArgument('id', InputArgument::REQUIRED, 'The ID of the failed job (or "all")')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$id = (string) $input->getArgument('id');
		$asJson = (bool) $input->getOption('json');

		try {
			$manager = new QueueManager();

			if ($id === 'all') {
				$count = $manager->retryAllFailed();
				if ($asJson) {
					return $this->outputJsonSuccess($output, ['retried' => $count]);
				}
				if ($count === 0) {
					\Laravel\Prompts\info('No failed jobs found.');
				} else {
					\Laravel\Prompts\info("Successfully re-queued {$count} job(s).");
				}
				return Command::SUCCESS;
			}

			$jobId = (int) $id;
			$retried = $manager->retryJob($jobId);

			if (!$retried) {
				$msg = "Failed job with ID [{$jobId}] not found.";
				if ($asJson) {
					return $this->outputJsonError($output, 'NOT_FOUND', $msg);
				}
				\Laravel\Prompts\error($msg);
				return Command::FAILURE;
			}

			if ($asJson) {
				return $this->outputJsonSuccess($output, ['id' => $jobId, 'retried' => true]);
			}

			\Laravel\Prompts\info("Job [{$jobId}] has been pushed back onto the queue.");
			return Command::SUCCESS;
		} catch (\Throwable $e) {
			$msg = 'Error retrying job: ' . $e->getMessage();
			if ($asJson) {
				return $this->outputJsonError($output, 'RETRY_FAILED', $msg);
			}
			\Laravel\Prompts\error($msg);
			return Command::FAILURE;
		}
	}
}
