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

final class LogListCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('log:list')
			->setDescription('List all available ProcessWire log files.')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$asJson = (bool) $input->getOption('json');
		$logs = \ProcessWire\wire('log')->getFiles();

		if (empty($logs)) {
			if ($asJson) {
				return $this->outputJsonSuccess($output, ['items' => [], 'total' => 0]);
			}
			warning('No log files found.');
			return Command::SUCCESS;
		}

		$headers = ['Log Name', 'Size', 'Modified'];
		$rows = [];
		$items = [];
		foreach ($logs as $name => $fileInfo) {
			$sizeBytes = (int) $fileInfo->getSize();
			$mtime = (int) $fileInfo->getMTime();
			$size = number_format($sizeBytes / 1024, 2) . ' kB';
			$modified = date('Y-m-d H:i:s', $mtime);
			$rows[] = [$name, $size, $modified];
			$items[] = [
				'name' => $name,
				'size' => $sizeBytes,
				'modified' => $mtime,
			];
		}

		if ($asJson) {
			return $this->outputJsonSuccess($output, ['items' => $items, 'total' => count($items)]);
		}

		table($headers, $rows);

		return Command::SUCCESS;
	}
}
