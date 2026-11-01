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
use function Laravel\Prompts\warning;

final class BackupPurgeCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('backup:purge')
			->setDescription('Purge old database backups by age (--days) or count (--keep).')
			->addOption('keep', null, InputOption::VALUE_REQUIRED, 'Keep the N most recent .sql backups and delete older ones')
			->addOption('days', null, InputOption::VALUE_REQUIRED, 'Delete .sql backups older than this many days', '30')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview backups to delete without removing them')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output')
			->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip interactive confirmations');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$keepOpt = $input->getOption('keep');
		$days = (int) $input->getOption('days');
		$dryRun = (bool) $input->getOption('dry-run');
		$asJson = (bool) $input->getOption('json');
		$force = (bool) $input->getOption('force');

		$dir = \ProcessWire\wire('config')->paths->assets . 'backups/database/';
		if (!is_dir($dir)) {
			if ($asJson) {
				return $this->outputJsonSuccess($output, ['deleted' => 0, 'files' => []]);
			}
			warning("Directory not found: {$dir}");
			return Command::SUCCESS;
		}

		$sqlFiles = [];
		foreach (scandir($dir) ?: [] as $f) {
			if (!str_ends_with(strtolower($f), '.sql')) {
				continue;
			}
			$path = $dir . $f;
			if (is_file($path)) {
				$sqlFiles[] = $path;
			}
		}

		// Sort newest first
		usort($sqlFiles, fn(string $a, string $b): int => (int) filemtime($b) <=> (int) filemtime($a));

		$candidates = [];
		if ($keepOpt !== null) {
			$keep = max(0, (int) $keepOpt);
			$candidates = array_slice($sqlFiles, $keep);
		} else {
			$threshold = time() - ($days * 86400);
			foreach ($sqlFiles as $path) {
				if ((int) filemtime($path) < $threshold) {
					$candidates[] = $path;
				}
			}
		}

		$candidateNames = array_map('basename', $candidates);

		if ($dryRun) {
			$data = [
				'dryRun' => true,
				'wouldDelete' => count($candidates),
				'files' => $candidateNames,
			];
			if ($asJson) {
				return $this->outputJsonSuccess($output, $data);
			}
			note('Dry-run: would delete ' . count($candidates) . ' backup file(s).');
			return Command::SUCCESS;
		}

		if (empty($candidates)) {
			$data = ['deleted' => 0, 'files' => []];
			if ($asJson) {
				return $this->outputJsonSuccess($output, $data);
			}
			info('No backup files matched for purging.');
			return Command::SUCCESS;
		}

		if (!$force) {
			if ($asJson) {
				return $this->outputJsonError($output, 'CONFIRMATION_REQUIRED', 'Use --force to run backup:purge non-interactively with --json.');
			}
			$desc = $keepOpt !== null
				? 'Delete ' . count($candidates) . " backup file(s), keeping the {$keepOpt} newest?"
				: 'Delete ' . count($candidates) . " file(s) older than {$days} days?";
			if (!confirm($desc, false)) {
				note('Aborted.');
				return Command::SUCCESS;
			}
		}

		$deleted = 0;
		$deletedFiles = [];
		foreach ($candidates as $p) {
			if (@unlink($p)) {
				$deleted++;
				$deletedFiles[] = basename($p);
			}
		}

		$data = [
			'deleted' => $deleted,
			'files' => $deletedFiles,
			'keep' => $keepOpt !== null ? (int) $keepOpt : null,
			'days' => $keepOpt === null ? $days : null,
		];

		if ($asJson) {
			return $this->outputJsonSuccess($output, $data);
		}

		info("Deleted {$deleted} backup file(s).");
		return Command::SUCCESS;
	}
}
