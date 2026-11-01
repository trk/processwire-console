<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Traits\InteractWithProcessWire;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;

final class DbBackupCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('db:backup')
			->setDescription('Create a database backup.')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$asJson = (bool) $input->getOption('json');
		$backup = \ProcessWire\wire('database')->backups();
		if (!$backup) {
			$msg = 'Database backup tool not available.';
			return $asJson ? $this->outputJsonError($output, 'UNAVAILABLE', $msg) : (error($msg) ?? Command::FAILURE);
		}

		if (!$asJson) {
			info('Creating database backup...');
		}

		$file = $backup->backup();

		if ($file) {
			if ($asJson) {
				return $this->outputJsonSuccess($output, ['file' => (string) $file]);
			}
			info("Database backup created: {$file}");
			return Command::SUCCESS;
		}

		$msg = 'Database backup failed.';
		return $asJson ? $this->outputJsonError($output, 'BACKUP_FAILED', $msg) : (error($msg) ?? Command::FAILURE);
	}
}
