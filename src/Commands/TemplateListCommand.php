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

final class TemplateListCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('template:list')
			->setDescription('List all templates in the ProcessWire installation.')
			->addOption('search', 's', InputOption::VALUE_REQUIRED, 'Search templates by name or tag')
			->addOption('tag', 't', InputOption::VALUE_REQUIRED, 'Filter by template tag')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$search = $input->getOption('search');
		$tag = $input->getOption('tag');
		$asJson = (bool) $input->getOption('json');

		$templates = \ProcessWire\wire('templates');
		$filtered = [];

		foreach ($templates as $template) {
			if ($tag !== null && $tag !== '' && stripos((string) $template->tags, (string) $tag) === false) {
				continue;
			}
			if ($search !== null && $search !== '' && (stripos($template->name, (string) $search) === false && stripos((string) $template->tags, (string) $search) === false)) {
				continue;
			}

			$filtered[] = $template;
		}

		if (empty($filtered)) {
			if ($asJson) {
				return $this->outputJsonSuccess($output, ['items' => [], 'total' => 0]);
			}
			warning('No templates found matching criteria.');
			return Command::SUCCESS;
		}

		$sanitizer = \ProcessWire\wire('sanitizer');

		if ($asJson) {
			$items = [];
			foreach ($filtered as $template) {
				$flags = [];
				if ($template->flags & \ProcessWire\Template::flagSystem) {
					$flags[] = 'sys';
				}
				$fieldCount = count($template->fields ?: []);
				$safeTpl = $sanitizer->selectorValue((string) $template->name);
				$pageCount = \ProcessWire\wire('pages')->count("template={$safeTpl}, include=all");
				$items[] = [
					'id' => (int) $template->id,
					'name' => (string) $template->name,
					'tag' => (string) ($template->tags ?: ''),
					'fields' => $fieldCount,
					'pages' => (int) $pageCount,
					'flags' => $flags,
				];
			}
			return $this->outputJsonSuccess($output, ['items' => $items, 'total' => count($filtered)]);
		}

		$rows = [];
		foreach ($filtered as $template) {
			$flags = [];
			if ($template->flags & \ProcessWire\Template::flagSystem) {
				$flags[] = 'sys';
			}
			$fieldCount = count($template->fields ?: []);
			$safeTpl = $sanitizer->selectorValue((string) $template->name);
			$pageCount = \ProcessWire\wire('pages')->count("template={$safeTpl}, include=all");
			$rows[] = [
				(string) $template->id,
				(string) $template->name,
				(string) ($template->tags ?: '-'),
				(string) $fieldCount,
				(string) $pageCount,
				implode('|', $flags) ?: '-',
			];
		}

		table(
			headers: ['ID', 'Name', 'Tag', 'Fields', 'Pages', 'Flags'],
			rows: $rows
		);

		note('Total templates: ' . count($filtered));

		return Command::SUCCESS;
	}
}
