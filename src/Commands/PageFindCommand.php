<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Traits\InteractWithProcessWire;
use function Laravel\Prompts\info;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;

final class PageFindCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('page:find')
			->setDescription('Find pages using a ProcessWire selector.')
			->addArgument('selector', InputArgument::OPTIONAL, 'The ProcessWire selector string')
			->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Maximum number of results when not set in selector', '50')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$selector = $input->getArgument('selector');
		$limit = max(1, min(500, (int) $input->getOption('limit')));
		$asJson = (bool) $input->getOption('json');

		if (!$selector && $this->canPrompt($input, $asJson)) {
			$selector = text('Selector string', required: true);
		}

		$selectorStr = trim((string) $selector);
		if ($selectorStr === '') {
			return $this->failCommand($output, $asJson, 'MISSING_SELECTOR', 'No selector provided.');
		}

		$effectiveSelector = $this->clampSelectorTopLevelLimit($selectorStr, $limit, 500);

		try {
			$pages = \ProcessWire\wire('pages')->find($effectiveSelector);
		} catch (\Throwable $e) {
			return $this->failCommand($output, $asJson, 'INVALID_SELECTOR', $e->getMessage());
		}

		if ($asJson) {
			$items = [];
			foreach ($pages as $page) {
				$tplName = $page->template instanceof \ProcessWire\Template ? (string) $page->template->name : '';
				$items[] = ['id' => (int) $page->id, 'path' => (string) $page->path, 'template' => $tplName];
			}
			return $this->outputJsonSuccess($output, [
				'items' => $items,
				'count' => count($items),
				'total' => (int) $pages->getTotal(),
			]);
		}

		$rows = [];
		foreach ($pages as $page) {
			$tplName = $page->template instanceof \ProcessWire\Template ? (string) $page->template->name : '';
			$rows[] = [
				(string) $page->id,
				(string) $page->path,
				$tplName,
			];
		}
		if ($rows === []) {
			info("No pages found matching '{$selectorStr}'.");
		} else {
			table(
				headers: ['ID', 'Path', 'Template'],
				rows: $rows
			);
			info('Found ' . count($pages) . ' pages.');
		}

		return Command::SUCCESS;
	}
}
