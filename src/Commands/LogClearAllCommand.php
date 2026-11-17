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
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output')
			->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip interactive confirmations');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$asJson = (bool) $input->getOption('json');
		$force = (bool) $input->getOption('force');

		$log = \ProcessWire\wire('log');
		$logs = $log->getLogs();

		if ($logs === []) {
			if ($asJson) {
				return $this->outputJsonSuccess($output, []);
			}
			note('No log files available to clear.');
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
				? $this->outputJsonSuccess($output, $results)
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
