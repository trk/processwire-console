<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Migration\Migrator;
use Totoglu\Console\Traits\InteractWithProcessWire;

final class MigrateInstallCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('migrate:install')
			->setDescription('Create the migration tracking table.')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$asJson = (bool) $input->getOption('json');
		$migrator = new Migrator();
		$created = $migrator->install();

		if (!$created) {
			if ($asJson) {
				return $this->outputJsonSuccess($output, ['created' => false, 'message' => 'Migration table already exists.']);
			}
			\Laravel\Prompts\note('Migration table already exists.');
			return Command::SUCCESS;
		}

		if ($asJson) {
			return $this->outputJsonSuccess($output, ['created' => true]);
		}

		\Laravel\Prompts\info('Migration table created.');

		return Command::SUCCESS;
	}
}
