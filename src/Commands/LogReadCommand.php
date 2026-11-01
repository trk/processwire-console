<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Traits\InteractWithProcessWire;
use function Laravel\Prompts\error;
use function Laravel\Prompts\note;
use function Laravel\Prompts\table;
use function ProcessWire\wire;

final class LogReadCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('log:read')
			->setDescription('Retrieve ProcessWire logs.')
			->addArgument('name', InputArgument::OPTIONAL, 'The name of the log (e.g. errors, messages)', null)
			->addArgument('limit', InputArgument::OPTIONAL, 'Number of entries to return', null)
			->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Number of entries to return', '10')
			->addOption('from', null, InputOption::VALUE_REQUIRED, 'Start date/time (e.g., 2026-04-01)')
			->addOption('to', null, InputOption::VALUE_REQUIRED, 'End date/time (e.g., today)')
			->addOption('find', null, InputOption::VALUE_REQUIRED, 'Filter entries containing text')
			->addOption('sort', null, InputOption::VALUE_REQUIRED, 'Sort direction (asc or desc)', 'desc')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$asJson = (bool) $input->getOption('json');
		$name = (string) $input->getArgument('name');

		if ($name === '' && !$asJson) {
			$name = $this->searchLog('Select a log file');
			if ($name === '') {
				return Command::SUCCESS;
			}
		}

		if ($name === '') {
			$msg = 'Provide log name.';
			return $asJson ? $this->outputJsonError($output, 'MISSING_NAME', $msg) : (error($msg) ?? Command::FAILURE);
		}

		$sanitizer = wire('sanitizer');
		$name = (string) preg_replace('/\.txt$/i', '', basename($name));
		$name = $sanitizer->name($name);

		$limitRaw = $input->getArgument('limit') ?? $input->getOption('limit');
		$limit = (int) $limitRaw;
		if ($limit < 1) {
			$limit = 10;
		}
		if ($limit > 5000) {
			$limit = 5000;
		}

		$sort = strtolower((string) $input->getOption('sort'));
		$from = $input->getOption('from');
		$to = $input->getOption('to');
		$find = $input->getOption('find');

		$options = ['limit' => $limit];
		if ($from) {
			$options['dateFrom'] = is_numeric($from) ? (int) $from : strtotime((string) $from);
		}
		if ($to) {
			$options['dateTo'] = is_numeric($to) ? (int) $to : strtotime((string) $to);
		}
		if ($find) {
			$options['text'] = (string) $find;
		}

		$log = wire('log');
		$entries = array_values($log->getEntries($name, $options));

		if (empty($entries)) {
			if ($asJson) {
				return $this->outputJsonSuccess($output, ['name' => $name, 'items' => [], 'total' => 0]);
			}
			note("No entries found in log: {$name}");
			return Command::SUCCESS;
		}

		if ($sort === 'desc') {
			$entries = array_reverse($entries);
		}

		if ($asJson) {
			return $this->outputJsonSuccess($output, [
				'name' => $name,
				'items' => $entries,
				'total' => count($entries),
			]);
		}

		$rows = [];
		foreach ($entries as $entry) {
			$rows[] = [
				$entry['date'] ?? '-',
				$entry['user'] ?? '-',
				$entry['url'] ?? '-',
				wordwrap((string) ($entry['text'] ?? '-'), 80, "\n", true),
			];
		}

		table(['Date', 'User', 'URL', 'Text'], $rows);

		return Command::SUCCESS;
	}
}
