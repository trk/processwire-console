<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Migration\Migrator;
use Totoglu\Console\Traits\InteractWithProcessWire;

final class MigrateRefreshCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('migrate:refresh')
			->setDescription('Reset all migrations and re-run them (rollback + migrate).')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview rollback and re-apply plan without mutating the database')
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
				$wouldRollback = $migrator->getResetOrder();
				$status = $migrator->getStatus();
			} catch (\Throwable $e) {
				return $this->failCommand($output, $asJson, 'REFRESH_FAILED', $e->getMessage());
			}
			$wouldApply = array_values(array_map(
				static fn(array $row): string => $row['name'],
				array_filter($status, static fn(array $row): bool => $row['status'] !== 'missing')
			));
			if ($asJson) {
				return $this->outputJsonSuccess($output, [
					'wouldRollback' => $wouldRollback,
					'wouldApply' => $wouldApply,
					'dryRun' => true,
				]);
			}
			\Laravel\Prompts\note('Dry-run: would rollback ' . count($wouldRollback) . ' and apply ' . count($wouldApply) . ' migration(s).');
			return Command::SUCCESS;
		}

		if (!$force) {
			if ($asJson || !$input->isInteractive()) {
				return $this->failCommand($output, $asJson, 'CONFIRMATION_REQUIRED', 'Use --force to run migrate:refresh non-interactively.');
			}
			if (!\Laravel\Prompts\confirm('This will rollback ALL migrations and re-run them. Continue?', default: false)) {
				\Laravel\Prompts\note('Aborted.');
				return Command::SUCCESS;
			}
		}

		try {
			$migrator = new Migrator();

			if (!$asJson) {
				\Laravel\Prompts\info('Rolling back all migrations...');
			}
			$resetResult = $migrator->reset();
		} catch (\Throwable $e) {
			return $this->failCommand($output, $asJson, 'REFRESH_FAILED', $e->getMessage());
		}

		if (!$asJson && !empty($resetResult['rolledBack'])) {
			\Laravel\Prompts\note('Rolling back');
			foreach ($resetResult['rolledBack'] as $file) {
				\Laravel\Prompts\warning("  ↩ {$file}");
			}
		}

		if (!empty($resetResult['errors'])) {
			if ($asJson) {
				return $this->outputJsonError($output, 'RESET_FAILED', implode('; ', $resetResult['errors']));
			}
			foreach ($resetResult['errors'] as $error) {
				\Laravel\Prompts\error($error);
			}
			return Command::FAILURE;
		}

		if (!$asJson) {
			\Laravel\Prompts\info('Running all migrations...');
		}
		try {
			$migrateResult = $migrator->runPending();
		} catch (\Throwable $e) {
			return $this->failCommand($output, $asJson, 'MIGRATION_FAILED', $e->getMessage());
		}

		if (!$asJson && !empty($migrateResult['applied'])) {
			\Laravel\Prompts\note('Migrating');
			foreach ($migrateResult['applied'] as $file) {
				\Laravel\Prompts\info("  ✓ {$file}");
			}
		}

		if ($asJson) {
			if (!empty($migrateResult['errors'])) {
				return $this->outputJsonError($output, 'MIGRATION_FAILED', implode('; ', $migrateResult['errors']));
			}
			return $this->outputJsonSuccess($output, [
				'rolledBack' => $resetResult['rolledBack'],
				'applied' => $migrateResult['applied'],
				'errors' => $migrateResult['errors'],
			]);
		}

		if (!empty($migrateResult['errors'])) {
			foreach ($migrateResult['errors'] as $error) {
				\Laravel\Prompts\error($error);
			}
			return Command::FAILURE;
		}

		\Laravel\Prompts\info('Refreshed: rolled back ' . count($resetResult['rolledBack']) . ', applied ' . count($migrateResult['applied']) . ' migration(s).');
		return Command::SUCCESS;
	}
}
