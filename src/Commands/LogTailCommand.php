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
use function Laravel\Prompts\error;

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
		$name = (string) $input->getArgument('name');
		$follow = (bool) $input->getOption('follow');
		$asJson = (bool) $input->getOption('json');

		$isInteractive = $name === '';

		if ($isInteractive && !$asJson) {
			$name = $this->searchLog('Select a log file');
			if ($name === '') {
				return Command::SUCCESS;
			}

			if (!$follow && $input->isInteractive()) {
				$follow = \Laravel\Prompts\confirm(
					label: 'Do you want to follow the output?',
					default: true,
					yes: 'Yes, tail the file continuously',
					no: 'No, just show recent lines'
				);
			}
		}

		if ($name === '') {
			$msg = 'Provide log name.';
			return $asJson ? $this->outputJsonError($output, 'MISSING_NAME', $msg) : (error($msg) ?? Command::FAILURE);
		}

		$file = (string) preg_replace('/\.txt$/i', '', basename($name));
		$linesCount = max(1, (int) $input->getOption('lines'));

		$path = \ProcessWire\wire('config')->paths->logs . $file . '.txt';
		if (!is_file($path)) {
			$msg = "Log file not found: {$path}";
			return $asJson ? $this->outputJsonError($output, 'NOT_FOUND', $msg) : (error($msg) ?? Command::FAILURE);
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
		while (true) {
			clearstatcache(true, $path);
			$size = (int) filesize($path);
			if ($size > $pos) {
				$fp = fopen($path, 'r');
				if ($fp) {
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
	}
}
