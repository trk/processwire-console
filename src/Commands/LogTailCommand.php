<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Support\FeatureDiscoverer;
use Totoglu\Console\Traits\InteractWithProcessWire;
use function Laravel\Prompts\confirm;

final class LogTailCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('log:tail')
			->setDescription('Tail a ProcessWire log file (like errors).')
			->addArgument('name', InputArgument::OPTIONAL, 'The name of the log (e.g. errors, messages)', null)
			->addOption('lines', null, InputOption::VALUE_REQUIRED, 'Number of lines to show from end', '200')
			->addOption('follow', 'f', InputOption::VALUE_NONE, 'Follow appended output')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$rawName = trim((string) $input->getArgument('name'));
		$follow = (bool) $input->getOption('follow');
		$asJson = (bool) $input->getOption('json');

		if ($rawName === '' && $this->canPrompt($input, $asJson)) {
			$rawName = $this->searchLog('Select a log file');
			if ($rawName === '') {
				return Command::SUCCESS;
			}

			if (!$follow) {
				$follow = confirm(
					label: 'Do you want to follow the output?',
					default: true,
					yes: 'Yes, tail the file continuously',
					no: 'No, just show recent lines'
				);
			}
		}

		if ($rawName === '') {
			return $this->failCommand($output, $asJson, 'MISSING_NAME', 'Provide log name.');
		}

		$file = $this->normalizeLogChannel($rawName);
		if ($file === '') {
			return $this->failCommand($output, $asJson, 'INVALID_NAME', "Invalid log name '{$rawName}'.");
		}

		$linesCount = max(1, min(10000, (int) $input->getOption('lines')));

		$path = \ProcessWire\wire('config')->paths->logs . $file . '.txt';
		if (!is_file($path) || is_link($path)) {
			return $this->failCommand($output, $asJson, 'NOT_FOUND', "Log file not found: {$path}");
		}

		$slice = FeatureDiscoverer::readLastLines($path, $linesCount);

		if ($asJson) {
			return $this->outputJsonSuccess($output, [
				'name' => $file,
				'lines' => $slice,
				'total' => count($slice),
			]);
		}

		foreach ($slice as $line) {
			$output->writeln($line);
		}

		if (!$follow) {
			return Command::SUCCESS;
		}

		$pos = (int) filesize($path);
		while (connection_aborted() === 0) {
			clearstatcache(true, $path);
			if (!is_file($path) || is_link($path)) {
				break;
			}
			$size = (int) filesize($path);
			if ($size < $pos) {
				$pos = $size;
			} elseif ($size > $pos) {
				$fp = fopen($path, 'r');
				if ($fp !== false) {
					fseek($fp, $pos);
					while (!feof($fp)) {
						$chunk = fgets($fp);
						if ($chunk !== false) {
							$output->writeln(rtrim($chunk, "\r\n"));
						}
					}
					fclose($fp);
				}
				$pos = $size;
			}
			usleep(500000);
		}

		return Command::SUCCESS;
	}
}
