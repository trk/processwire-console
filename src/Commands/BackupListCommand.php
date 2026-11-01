<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Traits\InteractWithProcessWire;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

final class BackupListCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('backup:list')
			->setDescription('List database backup files in site/assets/backups/database.')
			->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Limit number of entries', '50')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$limit = max(1, (int) $input->getOption('limit'));
		$asJson = (bool) $input->getOption('json');

		$dir = \ProcessWire\wire('config')->paths->assets . 'backups/database/';
		if (!is_dir($dir)) {
			if ($asJson) {
				return $this->outputJsonSuccess($output, ['items' => [], 'total' => 0]);
			}
			warning("Directory not found: {$dir}");
			return Command::SUCCESS;
		}

		$files = array_values(array_filter(
			scandir($dir) ?: [],
			fn(string $f): bool => str_ends_with(strtolower($f), '.sql') && is_file($dir . $f)
		));
		usort($files, fn(string $a, string $b): int => (int) filemtime($dir . $b) <=> (int) filemtime($dir . $a));
		$files = array_slice($files, 0, $limit);

		if (!$files) {
			if ($asJson) {
				return $this->outputJsonSuccess($output, ['items' => [], 'total' => 0]);
			}
			warning('No backup files found.');
			return Command::SUCCESS;
		}

		$rows = [];
		$items = [];
		foreach ($files as $f) {
			$sizeBytes = (int) filesize($dir . $f);
			$mtime = (int) filemtime($dir . $f);
			$rows[] = [$f, (int) ($sizeBytes / 1024), date('Y-m-d H:i', $mtime)];
			$items[] = [
				'file' => $f,
				'size' => $sizeBytes,
				'modified' => $mtime,
			];
		}

		if ($asJson) {
			return $this->outputJsonSuccess($output, ['items' => $items, 'total' => count($items)]);
		}

		table(
			['File', 'Size (KB)', 'Modified'],
			$rows
		);

		return Command::SUCCESS;
	}
}
