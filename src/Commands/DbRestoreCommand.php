<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Traits\InteractWithProcessWire;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
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
			->addOption('file', null, InputOption::VALUE_REQUIRED, 'Path to .sql file (required)')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only validate file access without executing')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output')
			->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip interactive confirmations');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$file = (string) $input->getOption('file');
		$dryRun = (bool) $input->getOption('dry-run');
		$asJson = (bool) $input->getOption('json');
		$force = (bool) $input->getOption('force');

		if ($file === '') {
			$msg = 'Provide --file.';
			return $asJson ? $this->outputJsonError($output, 'MISSING_FILE', $msg) : (error($msg) ?? Command::FAILURE);
		}
		if (str_ends_with(strtolower($file), '.gz') || !str_ends_with(strtolower($file), '.sql')) {
			$msg = 'Only extracted .sql files are supported.';
			return $asJson ? $this->outputJsonError($output, 'UNSUPPORTED_FORMAT', $msg) : (error($msg) ?? Command::FAILURE);
		}

		$assetsDir = \ProcessWire\wire('config')->paths->assets . 'backups/database/';
		$realAssetsDir = realpath($assetsDir);
		$realFile = realpath($file);
		if (
			$realFile === false
			|| $realAssetsDir === false
			|| !str_starts_with($realFile, rtrim($realAssetsDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)
			|| !is_file($realFile)
			|| !is_readable($realFile)
		) {
			$msg = 'SQL file must reside under site/assets/backups/database/ and be readable.';
			return $asJson ? $this->outputJsonError($output, 'FILE_NOT_READABLE', $msg) : (error($msg) ?? Command::FAILURE);
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
			if ($asJson) {
				return $this->outputJsonError($output, 'CONFIRMATION_REQUIRED', 'Use --force to run db:restore non-interactively with --json.');
			}
			if (!confirm('This will run SQL statements on your database. Continue?', false)) {
				note('Aborted.');
				return Command::SUCCESS;
			}
		}

		$db = \ProcessWire\wire('database');

		try {
			$backup = $db->backups();
			if (!$backup) {
				$msg = 'Database backup tool not available.';
				return $asJson ? $this->outputJsonError($output, 'BACKUP_UNAVAILABLE', $msg) : (error($msg) ?? Command::FAILURE);
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
				if ($asJson) {
					return $this->outputJsonError($output, 'RESTORE_FAILED', $msg);
				}
				error($msg);
				return Command::FAILURE;
			}
		} catch (\Throwable $e) {
			$msg = 'Restore failed: ' . $e->getMessage();
			if ($asJson) {
				return $this->outputJsonError($output, 'RESTORE_FAILED', $msg);
			}
			error($msg);
			return Command::FAILURE;
		}

		$data = ['file' => $realFile, 'restored' => true];
		if ($asJson) {
			return $this->outputJsonSuccess($output, $data);
		}
		info("Database restored from {$realFile}.");
		return Command::SUCCESS;
	}
}

