<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Migration\Migrator;
use Totoglu\Console\Traits\InteractWithProcessWire;

final class MigrateRollbackCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('migrate:rollback')
			->setDescription('Rollback the last batch of migrations.')
			->addOption('step', null, InputOption::VALUE_REQUIRED, 'Number of individual migrations to rollback (instead of batch)')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be rolled back')
			->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip interactive confirmations')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$steps = $input->getOption('step') ? (int) $input->getOption('step') : 0;
		$dryRun = (bool) $input->getOption('dry-run');
		$force = (bool) $input->getOption('force');
		$asJson = (bool) $input->getOption('json');

		$migrator = new Migrator();

		if ($dryRun) {
			$names = $migrator->getPendingRollback($steps);
			$data = ['toRollback' => $names, 'count' => count($names)];

			if ($asJson) {
				return $this->outputJsonSuccess($output, $data);
			}
			if (empty($names)) {
				\Laravel\Prompts\note('Nothing to rollback.');
			} else {
				\Laravel\Prompts\note('Dry-run: ' . count($names) . ' migration(s) would be rolled back:');
				foreach ($names as $name) {
					\Laravel\Prompts\warning("  ↩ {$name}");
				}
			}
			return Command::SUCCESS;
		}

		if (!$force) {
			if ($asJson) {
				return $this->outputJsonError($output, 'CONFIRMATION_REQUIRED', 'Use --force to run migrate:rollback non-interactively with --json.');
			}
			if (!\Laravel\Prompts\confirm('Rollback migrations?', default: false)) {
				\Laravel\Prompts\note('Aborted.');
				return Command::SUCCESS;
			}
		}

		if (!$asJson) {
			\Laravel\Prompts\info('Rolling back migrations...');
		}

		$result = $steps > 0
			? $migrator->rollbackSteps($steps)
			: $migrator->rollbackLastBatch();

		if ($asJson) {
			if (!empty($result['errors'])) {
				return $this->outputJsonError($output, 'ROLLBACK_FAILED', implode('; ', $result['errors']));
			}
			return $this->outputJsonSuccess($output, $result);
		}

		if (empty($result['rolledBack']) && empty($result['errors'])) {
			\Laravel\Prompts\note('Nothing to rollback.');
			return Command::SUCCESS;
		}

		foreach ($result['rolledBack'] as $file) {
			\Laravel\Prompts\warning("  ↩ {$file}");
		}

		if (!empty($result['errors'])) {
			foreach ($result['errors'] as $error) {
				\Laravel\Prompts\error($error);
			}
			return Command::FAILURE;
		}

		\Laravel\Prompts\info('Rolled back ' . count($result['rolledBack']) . ' migration(s).');
		return Command::SUCCESS;
	}
}
