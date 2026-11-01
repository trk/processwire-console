<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Migration\Migrator;
use Totoglu\Console\Traits\InteractWithProcessWire;

final class MigrateFreshCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('migrate:fresh')
			->setDescription('Drop the migrations table and re-run all migrations from scratch.')
			->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip interactive confirmations')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$force = (bool) $input->getOption('force');
		$asJson = (bool) $input->getOption('json');

		if (!$force) {
			if ($asJson) {
				return $this->outputJsonError($output, 'CONFIRMATION_REQUIRED', 'Use --force to run migrate:fresh non-interactively with --json.');
			}
			if (!\Laravel\Prompts\confirm('This will drop the migrations table and re-run ALL migrations. Continue?', default: false)) {
				\Laravel\Prompts\note('Aborted.');
				return Command::SUCCESS;
			}
		}

		$migrator = new Migrator();

		if (!$asJson) {
			\Laravel\Prompts\info('Dropping migrations table and running all migrations...');
		}

		$result = $migrator->fresh();

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
			return Command::FAILURE;
		}

		\Laravel\Prompts\info('Fresh migration: applied ' . count($result['applied']) . ' migration(s).');
		return Command::SUCCESS;
	}
}
