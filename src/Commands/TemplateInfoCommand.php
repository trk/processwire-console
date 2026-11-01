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
use function Laravel\Prompts\note;
use function Laravel\Prompts\table;

final class TemplateInfoCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('template:info')
			->setDescription('Show detailed information about a template.')
			->addArgument('name', InputArgument::OPTIONAL, 'The name of the template')
			->addOption('name', null, InputOption::VALUE_OPTIONAL, 'The name of the template')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$asJson = (bool) $input->getOption('json');
		$name = $input->getOption('name') ?: $input->getArgument('name');

		if (!$name && !$asJson) {
			$name = $this->searchTemplate('Select the template to view info');
			if ($name === '') {
				return Command::SUCCESS;
			}
		}

		if (!$name) {
			$msg = 'Provide template name via argument or --name.';
			return $asJson ? $this->outputJsonError($output, 'MISSING_NAME', $msg) : (error($msg) ?? Command::FAILURE);
		}

		$template = \ProcessWire\wire('templates')->get((string) $name);

		if (!$template || !$template->id) {
			$msg = "Template '{$name}' not found.";
			return $asJson ? $this->outputJsonError($output, 'NOT_FOUND', $msg) : (error($msg) ?? Command::FAILURE);
		}

		$pageCount = (int) \ProcessWire\wire('pages')->count("template={$template->name}, include=all");
		$fieldList = [];
		$fieldItems = [];
		foreach ($template->fields as $f) {
			$fieldList[] = [$f->name, $f->type->className()];
			$fieldItems[] = ['name' => $f->name, 'type' => $f->type->className()];
		}

		$flags = [];
		if ($template->flags & \ProcessWire\Template::flagSystem) {
			$flags[] = 'System';
		}
		if ($template->noParents) {
			$flags[] = 'No Parents';
		}
		if ($template->noChildren) {
			$flags[] = 'No Children';
		}

		if ($asJson) {
			return $this->outputJsonSuccess($output, [
				'id' => $template->id,
				'name' => $template->name,
				'label' => (string) ($template->label ?: ''),
				'tag' => (string) ($template->tags ?: ''),
				'file' => (string) ($template->filename ?: ''),
				'fieldsCount' => count($fieldItems),
				'pagesCount' => $pageCount,
				'fields' => $fieldItems,
				'flags' => $flags,
			]);
		}

		info("Template Structure: {$template->name}");

		$general = [
			'ID' => $template->id,
			'Name' => $template->name,
			'Label' => $template->label ?: '-',
			'Tag' => $template->tags ?: '-',
			'File' => $template->filename ?: 'No file',
			'Fields' => count($fieldList),
			'Pages' => $pageCount,
		];

		table(
			headers: ['Property', 'Value'],
			rows: array_map(static fn(string $k, mixed $v): array => [$k, $v], array_keys($general), array_values($general))
		);

		info('Fields (Order)');
		if ($fieldList) {
			table(
				headers: ['Name', 'Type'],
				rows: $fieldList
			);
		} else {
			note('No fields defined');
		}

		info('Access Control');
		if ($template->useRoles) {
			$accessRows = [
				['View Roles', implode(', ', array_map(static fn(object $r): string => (string) $r->name, iterator_to_array($template->roles)))],
				['Edit Roles', implode(', ', array_map(static fn(object $r): string => (string) $r->name, iterator_to_array($template->editRoles)))],
			];
			table(
				headers: ['Permission', 'Roles'],
				rows: $accessRows
			);
		} else {
			note('Inheriting access from parent.');
		}

		if ($flags) {
			info('Technical Flags');
			note(implode(' | ', $flags));
		}

		return Command::SUCCESS;
	}
}
