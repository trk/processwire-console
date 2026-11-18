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

final class LogClearAllCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('log:clear-all')
			->setDescription('Clear all ProcessWire log files.')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write changes')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output')
			->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip interactive confirmations');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$dryRun = (bool) $input->getOption('dry-run');
		$asJson = (bool) $input->getOption('json');
		$force = (bool) $input->getOption('force');

		$log = \ProcessWire\wire('log');
		$logs = $log->getLogs();

		if ($logs === []) {
			if ($asJson) {
				return $this->outputJsonSuccess($output, $dryRun ? ['channels' => [], 'dryRun' => true] : ['channels' => [], 'cleared' => 0]);
			}
			note('No log files available to clear.');
			return Command::SUCCESS;
		}

		if ($dryRun) {
			$channels = array_values(array_filter(array_map(
				fn($name): string => $this->normalizeLogChannel((string) $name),
				array_keys($logs)
			)));
			if ($asJson) {
				return $this->outputJsonSuccess($output, ['channels' => $channels, 'dryRun' => true]);
			}
			note('Dry-run: would clear ' . count($channels) . ' log files.');
			return Command::SUCCESS;
		}

		if (!$force) {
			if ($asJson || !$input->isInteractive()) {
				return $this->failCommand($output, $asJson, 'CONFIRMATION_REQUIRED', 'Use --force to run log:clear-all non-interactively.');
			}
			if (!confirm('Clear ' . count($logs) . ' log files?', false)) {
				note('Aborted.');
				return Command::SUCCESS;
			}
		}

		$results = [];
		$allOk = true;
		$logsDir = \ProcessWire\wire('config')->paths->logs;

		foreach ($logs as $name => $fileInfo) {
			$file = $this->normalizeLogChannel((string) $name);
			if ($file === '') {
				continue;
			}
			$path = $logsDir . $file . '.txt';

			if (!is_file($path) || is_link($path)) {
				$results[$file] = false;
				$allOk = false;
				continue;
			}

			$ok = @file_put_contents($path, '') !== false;
			$results[$file] = $ok;
			if (!$ok) {
				$allOk = false;
			}
		}

		if ($asJson) {
			return $allOk
				? $this->outputJsonSuccess($output, ['channels' => $results, 'cleared' => count($results)])
				: $this->outputJsonError($output, 'CLEAR_FAILED', 'Failed to clear some logs.');
		}
		if ($allOk) {
			info('Cleared all logs.');
		} else {
			error('Failed to clear some logs.');
		}

		return $allOk ? Command::SUCCESS : Command::FAILURE;
	}
}
