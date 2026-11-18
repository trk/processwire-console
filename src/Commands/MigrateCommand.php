<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Migration\Migrator;
use Totoglu\Console\Traits\InteractWithProcessWire;

final class MigrateCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('migrate')
			->setDescription('Run all pending migrations.')
			->addOption('step', null, InputOption::VALUE_REQUIRED, 'Number of migrations to run (default: all)')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show pending migrations without applying')
			->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip interactive confirmations')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$stepOpt = $input->getOption('step');
		$dryRun = (bool) $input->getOption('dry-run');
		$force = (bool) $input->getOption('force');
		$asJson = (bool) $input->getOption('json');

		$steps = 0;
		if ($stepOpt !== null && $stepOpt !== '') {
			$stepStr = trim((string) $stepOpt);
			if (!ctype_digit($stepStr) || (int) $stepStr < 1) {
				return $this->failCommand($output, $asJson, 'INVALID_STEP', 'Option --step must be a positive integer.');
			}
			$steps = (int) $stepStr;
		}

		try {
			$migrator = new Migrator();
			$pending = $migrator->getPending();
		} catch (\Throwable $e) {
			return $this->failCommand($output, $asJson, 'MIGRATION_FAILED', $e->getMessage());
		}

		if ($pending === []) {
			if ($asJson) {
				return $this->outputJsonSuccess($output, ['applied' => [], 'message' => 'Nothing to migrate.']);
			}
			\Laravel\Prompts\info('Nothing to migrate.');
			return Command::SUCCESS;
		}

		if ($steps > 0) {
			$pending = array_slice($pending, 0, $steps);
		}

		if ($dryRun) {
			$data = ['pending' => $pending, 'count' => count($pending)];
			if ($asJson) {
				return $this->outputJsonSuccess($output, $data);
			}
			\Laravel\Prompts\note('Dry-run: ' . count($pending) . ' migration(s) would be applied:');
			foreach ($pending as $file) {
				\Laravel\Prompts\info("  → {$file}");
			}
			return Command::SUCCESS;
		}

		if ($this->canPrompt($input, $asJson, $force)) {
			\Laravel\Prompts\note(count($pending) . ' pending migration(s):');
			foreach ($pending as $file) {
				\Laravel\Prompts\info("  → {$file}");
			}
			if (!\Laravel\Prompts\confirm('Apply these migrations?', default: true)) {
				\Laravel\Prompts\note('Aborted.');
				return Command::SUCCESS;
			}
		}

		if (!$asJson) {
			\Laravel\Prompts\info('Running migrations...');
		}
		try {
			$result = $migrator->runPending($steps);
		} catch (\Throwable $e) {
			return $this->failCommand($output, $asJson, 'MIGRATION_FAILED', $e->getMessage());
		}

		if ($asJson) {
			if (!empty($result['errors'])) {
				return $this->outputJsonError($output, 'MIGRATION_FAILED', implode('; ', $result['errors']));
			}
			return $this->outputJsonSuccess($output, $result);
		}

		foreach ($result['applied'] as $file) {
			\Laravel\Prompts\info("  ✓ {$file}");
		}

		if (!empty($result['errors'])) {
			foreach ($result['errors'] as $error) {
				\Laravel\Prompts\error($error);
			}
			\Laravel\Prompts\warning('Applied ' . count($result['applied']) . ' migration(s) before error.');
			return Command::FAILURE;
		}

		\Laravel\Prompts\info('Applied ' . count($result['applied']) . ' migration(s).');
		return Command::SUCCESS;
	}
}
