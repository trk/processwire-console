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
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$selector = $input->getArgument('selector');
		$asJson = (bool) $input->getOption('json');

		if (!$selector && $input->isInteractive() && !$asJson) {
			$selector = text('Selector string', required: true);
		}

		if (!$selector) {
			$msg = 'No selector provided.';
			return $asJson ? $this->outputJsonError($output, 'MISSING_SELECTOR', $msg) : (error($msg) ?? Command::FAILURE);
		}

		$pages = \ProcessWire\wire('pages')->find((string) $selector);

		if ($asJson) {
			$items = [];
			foreach ($pages as $page) {
				$items[] = ['id' => $page->id, 'path' => $page->path, 'template' => $page->template->name];
			}
			return $this->outputJsonSuccess($output, ['items' => $items, 'count' => count($items)]);
		}

		$rows = [];
		foreach ($pages as $page) {
			$rows[] = [
				$page->id,
				$page->path,
				$page->template->name,
			];
		}
		if (empty($rows)) {
			info("No pages found matching '{$selector}'.");
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
