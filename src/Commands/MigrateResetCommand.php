<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Migration\Migrator;
use Totoglu\Console\Traits\InteractWithProcessWire;

final class MigrateResetCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('migrate:reset')
			->setDescription('Rollback all applied migrations.')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview applied migrations that would be rolled back')
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
				$migrator = new Migrator();
				$reversed = $migrator->getResetOrder();
			} catch (\Throwable $e) {
				return $this->failCommand($output, $asJson, 'RESET_FAILED', $e->getMessage());
			}
			if ($asJson) {
				return $this->outputJsonSuccess($output, ['wouldRollback' => $reversed, 'dryRun' => true]);
			}
			\Laravel\Prompts\note('Dry-run: would rollback ' . count($reversed) . ' migration(s).');
			return Command::SUCCESS;
		}

		if (!$force) {
			if ($asJson || !$input->isInteractive()) {
				return $this->failCommand($output, $asJson, 'CONFIRMATION_REQUIRED', 'Use --force to run migrate:reset non-interactively.');
			}
			if (!\Laravel\Prompts\confirm('This will rollback ALL applied migrations. Continue?', default: false)) {
				\Laravel\Prompts\note('Aborted.');
				return Command::SUCCESS;
			}
		}

		try {
			$migrator = new Migrator();

			if (!$asJson) {
				\Laravel\Prompts\info('Resetting all migrations...');
			}
			$result = $migrator->reset();
		} catch (\Throwable $e) {
			return $this->failCommand($output, $asJson, 'RESET_FAILED', $e->getMessage());
		}

		if ($asJson) {
			if (!empty($result['errors'])) {
				return $this->outputJsonError($output, 'RESET_FAILED', implode('; ', $result['errors']));
			}
			return $this->outputJsonSuccess($output, $result);
		}

		if (empty($result['rolledBack']) && empty($result['errors'])) {
			\Laravel\Prompts\note('Nothing to reset.');
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

		\Laravel\Prompts\info('Reset ' . count($result['rolledBack']) . ' migration(s).');
		return Command::SUCCESS;
	}
}
