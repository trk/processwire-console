<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Traits\InteractWithProcessWire;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\confirm;

final class LogClearCommand extends Command
{
	use InteractWithProcessWire;
	protected function configure(): void
	{
		$this
			->setName('log:clear')
			->setDescription('Clear a ProcessWire log file.')
			->addArgument('name', InputArgument::IS_ARRAY, 'The name of the logs (e.g. errors messages)', [])
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write changes')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output')
			->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip interactive confirmations');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$names = array_values(array_map('strval', (array) $input->getArgument('name')));
		$dryRun = (bool) $input->getOption('dry-run');
		$asJson = (bool) $input->getOption('json');
		$force = (bool) $input->getOption('force');

		if ($names === [] && $this->canPrompt($input, $asJson, $force)) {
			$names = $this->multiselectLogs('Select log files to clear');
		}

		if ($names === []) {
			return $this->failCommand($output, $asJson, 'MISSING_NAME', 'Provide at least one log name.');
		}

		$logsDir = (string) \ProcessWire\wire('config')->paths->logs;
		$realLogsDir = realpath($logsDir);
		$resolvedTargets = [];

		foreach ($names as $name) {
			$file = $this->normalizeLogChannel($name);
			if ($file === '') {
				return $this->failCommand($output, $asJson, 'INVALID_NAME', "Invalid log name: {$name}");
			}

			$path = $logsDir . $file . '.txt';
			$realPath = realpath($path);
			if (
				!is_file($path)
				|| is_link($path)
				|| $realLogsDir === false
				|| $realPath === false
				|| !str_starts_with($realPath, rtrim($realLogsDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)
			) {
				return $this->failCommand($output, $asJson, 'NOT_FOUND', "Log file not found: {$file}");
			}

			$resolvedTargets[$file] = $realPath;
		}

		if ($dryRun) {
			$preview = ['channels' => array_keys($resolvedTargets), 'dryRun' => true];
			if ($asJson) {
				return $this->outputJsonSuccess($output, $preview);
			}
			note('Dry-run: would clear logs: ' . implode(', ', array_keys($resolvedTargets)) . '.');
			return Command::SUCCESS;
		}

		if (!$force) {
			if ($asJson || !$input->isInteractive()) {
				return $this->failCommand($output, $asJson, 'CONFIRMATION_REQUIRED', 'Use --force to run log:clear non-interactively.');
			}
			$list = implode(', ', array_keys($resolvedTargets));
			if (!confirm("Clear log files '{$list}'?", false)) {
				note('Aborted.');
				return Command::SUCCESS;
			}
		}

		$results = [];
		$allOk = true;

		foreach ($resolvedTargets as $file => $realPath) {
			$ok = @file_put_contents($realPath, '') !== false;
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
			info('Cleared selected logs.');
		} else {
			error('Failed to clear some logs.');
		}
		return $allOk ? Command::SUCCESS : Command::FAILURE;
	}
}

