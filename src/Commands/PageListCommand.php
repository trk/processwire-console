<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Traits\InteractWithProcessWire;
use function Laravel\Prompts\note;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

final class PageListCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('page:list')
			->setDescription('List pages in the ProcessWire installation with advanced filtering.')
			->addOption('template', 't', InputOption::VALUE_REQUIRED, 'Filter by template name')
			->addOption('parent', 'p', InputOption::VALUE_REQUIRED, 'Filter by parent path or ID')
			->addOption('sort', 's', InputOption::VALUE_REQUIRED, 'Sort field (e.g., -created, title)', '-created')
			->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Number of pages to show', '20')
			->addOption('include', 'i', InputOption::VALUE_REQUIRED, 'Include hidden/all/unpublished', 'all')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$template = $input->getOption('template');
		$parent = $input->getOption('parent');
		$sort = $input->getOption('sort');
		$limit = $input->getOption('limit');
		$include = $input->getOption('include');
		$asJson = (bool) $input->getOption('json');

		$sanitizer = \ProcessWire\wire('sanitizer');

		$selectorParts = [];
		if ($template) {
			$safeTemplate = $sanitizer->selectorValue((string) $template);
			$selectorParts[] = "template={$safeTemplate}";
		}
		if ($parent) {
			$safeParent = $sanitizer->selectorValue((string) $parent);
			$selectorParts[] = "parent={$safeParent}";
		}
		if ($sort) {
			$sortStr = (string) $sort;
			$desc = str_starts_with($sortStr, '-');
			$rawField = $desc ? substr($sortStr, 1) : $sortStr;
			$safeSort = $sanitizer->selectorValue($rawField);
			$selectorParts[] = 'sort=' . ($desc ? '-' : '') . $safeSort;
		}
		if ($limit) {
			$selectorParts[] = 'limit=' . max(1, (int) $limit);
		}
		if ($include) {
			$safeInclude = $sanitizer->selectorValue((string) $include);
			$selectorParts[] = "include={$safeInclude}";
		}

		$selector = implode(', ', $selectorParts);

		$pages = \ProcessWire\wire('pages')->find($selector ?: 'limit=20, sort=-created');

		if (!$pages->count()) {
			if ($asJson) {
				return $this->outputJsonSuccess($output, [
					'items' => [],
					'total' => 0,
				]);
			}
			warning("No pages found matching selector: [{$selector}]");
			return Command::SUCCESS;
		}

		if ($asJson) {
			$items = [];
			foreach ($pages as $page) {
				$status = [];
				if ($page->isHidden()) {
					$status[] = 'hidden';
				}
				if ($page->isUnpublished()) {
					$status[] = 'unpub';
				}
				if ($page->isLocked()) {
					$status[] = 'locked';
				}
				$items[] = [
					'id' => $page->id,
					'path' => $page->path,
					'title' => (string) ($page->title ?: $page->name),
					'template' => $page->template->name,
					'status' => $status ?: ['pub'],
					'modified' => (int) $page->modified,
				];
			}
			return $this->outputJsonSuccess($output, [
				'items' => $items,
				'total' => $pages->getTotal(),
				'selector' => $selector ?: 'standard',
			]);
		}

		$rows = [];
		foreach ($pages as $page) {
			$status = [];
			if ($page->isHidden()) {
				$status[] = 'hidden';
			}
			if ($page->isUnpublished()) {
				$status[] = 'unpub';
			}
			if ($page->isLocked()) {
				$status[] = 'locked';
			}
			$rows[] = [
				$page->id,
				$page->path,
				mb_strimwidth($page->title ?: $page->name, 0, 30, '...'),
				$page->template->name,
				implode('|', $status) ?: 'pub',
				date('Y-m-d H:i', (int) $page->modified),
			];
		}
		table(['ID', 'Path', 'Title', 'Template', 'Status', 'Modified'], $rows);
		note('Found ' . $pages->getTotal() . ' total pages for selector: [' . ($selector ?: 'standard') . ']');

		return Command::SUCCESS;
	}
}
