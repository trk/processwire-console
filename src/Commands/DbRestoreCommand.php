<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Traits\InteractWithProcessWire;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;

final class DbRestoreCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('db:restore')
			->setDescription('Restore database from a .sql backup file.')
			->addOption('file', null, InputOption::VALUE_REQUIRED, 'Path or filename of .sql backup (required)')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only validate file access without executing')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output')
			->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip interactive confirmations');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$file = trim((string) $input->getOption('file'));
		$dryRun = (bool) $input->getOption('dry-run');
		$asJson = (bool) $input->getOption('json');
		$force = (bool) $input->getOption('force');

		if ($file === '') {
			return $this->failCommand($output, $asJson, 'MISSING_FILE', 'Provide --file.');
		}
		if (str_ends_with(strtolower($file), '.gz') || !str_ends_with(strtolower($file), '.sql')) {
			return $this->failCommand($output, $asJson, 'UNSUPPORTED_FORMAT', 'Only extracted .sql files are supported.');
		}

		$assetsDir = (string) \ProcessWire\wire('config')->paths->assets . 'backups/database/';
		$realAssetsDir = realpath($assetsDir);
		$candidatePath = (!str_contains($file, '/') && !str_contains($file, '\\'))
			? $assetsDir . basename($file)
			: $file;
		$realFile = realpath($candidatePath);

		if (
			is_link($candidatePath)
			|| $realFile === false
			|| $realAssetsDir === false
			|| dirname($realFile) !== rtrim($realAssetsDir, DIRECTORY_SEPARATOR)
			|| !is_file($realFile)
			|| !is_readable($realFile)
		) {
			return $this->failCommand(
				$output,
				$asJson,
				'FILE_NOT_READABLE',
				'SQL file must reside under site/assets/backups/database/ and be readable.'
			);
		}

		if ($dryRun) {
			$data = ['file' => $realFile, 'validated' => true];
			if ($asJson) {
				return $this->outputJsonSuccess($output, $data);
			}
			info('Validated SQL file: ' . $realFile);
			return Command::SUCCESS;
		}

		if (!$force) {
			if ($asJson || !$input->isInteractive()) {
				return $this->failCommand($output, $asJson, 'CONFIRMATION_REQUIRED', 'Use --force to run db:restore non-interactively.');
			}
			if (!confirm('This will run SQL statements on your database. Continue?', default: false)) {
				note('Aborted.');
				return Command::SUCCESS;
			}
		}

		$db = \ProcessWire\wire('database');

		try {
			$backup = $db->backups();
			if (!$backup) {
				return $this->failCommand($output, $asJson, 'BACKUP_UNAVAILABLE', 'Database backup tool not available.');
			}
			if (!$asJson) {
				info("Restoring database from {$realFile}...");
			}
			$result = $backup->restore(basename($realFile));
			if (!$result) {
				$msg = 'Restore returned no result — check the SQL file.';
				$errors = $backup->errors();
				if ($errors) {
					$msg .= "\n" . implode("\n", $errors);
				}
				return $this->failCommand($output, $asJson, 'RESTORE_FAILED', $msg);
			}
		} catch (\Throwable $e) {
			return $this->failCommand($output, $asJson, 'RESTORE_FAILED', 'Restore failed: ' . $e->getMessage());
		}

		$data = ['file' => $realFile, 'restored' => true];
		if ($asJson) {
			return $this->outputJsonSuccess($output, $data);
		}
		info("Database restored from {$realFile}.");
		return Command::SUCCESS;
	}
}
