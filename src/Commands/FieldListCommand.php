<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Traits\InteractWithProcessWire;
use function Laravel\Prompts\info;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

final class FieldListCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('field:list')
			->setDescription('List all fields in the ProcessWire installation.')
			->addOption('type', 't', InputOption::VALUE_REQUIRED, 'Filter by field type (e.g., FieldtypeText)')
			->addOption('search', 's', InputOption::VALUE_REQUIRED, 'Search fields by name or label')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$type = $input->getOption('type');
		$search = $input->getOption('search');
		$asJson = (bool) $input->getOption('json');

		$fields = \ProcessWire\wire('fields');
		$filtered = [];

		foreach ($fields as $field) {
			$typeName = $field->type ? $field->type->className() : 'Unknown';
			if ($type && stripos($typeName, (string) $type) === false) {
				continue;
			}
			if ($search && (stripos((string) $field->name, (string) $search) === false && stripos((string) $field->label, (string) $search) === false)) {
				continue;
			}

			$filtered[] = $field;
		}

		if ($filtered === []) {
			if ($asJson) {
				return $this->outputJsonSuccess($output, ['items' => [], 'total' => 0]);
			}
			warning('No fields found matching criteria.');
			return Command::SUCCESS;
		}

		if ($asJson) {
			$items = [];
			foreach ($filtered as $field) {
				$templates = array_map(static fn(\ProcessWire\Template $t): string => (string) $t->name, iterator_to_array($field->getTemplates()));
				$flags = [];
				if ($field->flags & \ProcessWire\Field::flagSystem) {
					$flags[] = 'sys';
				}
				if ($field->flags & \ProcessWire\Field::flagPermanent) {
					$flags[] = 'perm';
				}
				$items[] = [
					'id' => (int) $field->id,
					'name' => (string) $field->name,
					'type' => $field->type ? $field->type->className() : 'Unknown',
					'label' => (string) $field->label,
					'contexts' => count($templates),
					'flags' => $flags,
				];
			}
			return $this->outputJsonSuccess($output, ['items' => $items, 'total' => count($filtered)]);
		}

		$headers = ['ID', 'Name', 'Type', 'Label', 'Contexts', 'Flags'];
		$rows = [];

		foreach ($filtered as $field) {
			$templates = array_map(static fn(\ProcessWire\Template $t): string => (string) $t->name, iterator_to_array($field->getTemplates()));
			$flags = [];
			if ($field->flags & \ProcessWire\Field::flagSystem) {
				$flags[] = 'sys';
			}
			if ($field->flags & \ProcessWire\Field::flagPermanent) {
				$flags[] = 'perm';
			}

			$rows[] = [
				(string) $field->id,
				(string) $field->name,
				$field->type ? $field->type->className() : 'Unknown',
				mb_strimwidth((string) $field->label, 0, 30, '...'),
				(string) count($templates),
				implode('|', $flags) ?: '-',
			];
		}

		table(headers: $headers, rows: $rows);
		info('Total fields: ' . count($filtered));

		return Command::SUCCESS;
	}
}
