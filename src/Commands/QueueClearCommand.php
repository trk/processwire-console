<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Queue\QueueManager;
use Totoglu\Console\Traits\InteractWithProcessWire;

final class QueueClearCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('queue:clear')
			->setDescription('Clear all failed jobs from the failed_jobs database table.')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview failed job count without deleting')
			->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip interactive confirmations')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$dryRun = (bool) $input->getOption('dry-run');
		$force = (bool) $input->getOption('force');
		$asJson = (bool) $input->getOption('json');

		if ($dryRun) {
			try {
				$manager = new QueueManager();
				$count = $manager->countFailedJobs();
				if ($asJson) {
					return $this->outputJsonSuccess($output, ['wouldDelete' => $count, 'dryRun' => true]);
				}
				\Laravel\Prompts\note("Dry-run: would clear {$count} failed job(s).");
				return Command::SUCCESS;
			} catch (\Throwable $e) {
				return $this->failCommand($output, $asJson, 'CLEAR_FAILED', 'Failed to inspect failed jobs: ' . $e->getMessage());
			}
		}

		if (!$force) {
			if ($asJson || !$input->isInteractive()) {
				return $this->failCommand($output, $asJson, 'CONFIRMATION_REQUIRED', 'Use --force to run queue:clear non-interactively.');
			}
			if (!\Laravel\Prompts\confirm('Are you sure you want to delete all failed jobs? This action cannot be undone.', default: false)) {
				\Laravel\Prompts\note('Action canceled.');
				return Command::SUCCESS;
			}
		}

		try {
			$manager = new QueueManager();
			$manager->ensureTables();
			$deleted = $manager->clearFailedJobs();

			if ($asJson) {
				return $this->outputJsonSuccess($output, ['cleared' => true, 'deleted' => $deleted]);
			}

			\Laravel\Prompts\info('Failed jobs have been successfully cleared.');
			return Command::SUCCESS;
		} catch (\Throwable $e) {
			return $this->failCommand($output, $asJson, 'CLEAR_FAILED', 'Failed to clear jobs: ' . $e->getMessage());
		}
	}
}
