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
			->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip interactive confirmations')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$force = (bool) $input->getOption('force');
		$asJson = (bool) $input->getOption('json');

		if (!$force) {
			if ($asJson) {
				return $this->outputJsonError($output, 'CONFIRMATION_REQUIRED', 'Use --force to run queue:clear non-interactively with --json.');
			}
			if (!\Laravel\Prompts\confirm('Are you sure you want to delete all failed jobs? This action cannot be undone.', default: false)) {
				\Laravel\Prompts\note('Action canceled.');
				return Command::SUCCESS;
			}
		}

		try {
			$manager = new QueueManager();
			$deleted = $manager->clearFailedJobs();

			if ($asJson) {
				return $this->outputJsonSuccess($output, ['cleared' => true, 'deleted' => $deleted]);
			}

			\Laravel\Prompts\info('Failed jobs have been successfully cleared.');
			return Command::SUCCESS;
		} catch (\Throwable $e) {
			$msg = 'Failed to clear jobs: ' . $e->getMessage();
			if ($asJson) {
				return $this->outputJsonError($output, 'CLEAR_FAILED', $msg);
			}
			\Laravel\Prompts\error($msg);
			return Command::FAILURE;
		}
	}
}
