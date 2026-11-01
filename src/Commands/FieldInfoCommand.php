<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use ProcessWire\Field;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Traits\InteractWithProcessWire;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;
use function ProcessWire\wire;

final class FieldInfoCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('field:info')
			->setDescription('Show detailed information about a field, including technical details.')
			->addArgument('name', InputArgument::OPTIONAL, 'The name of the field')
			->addOption('name', null, InputOption::VALUE_OPTIONAL, 'The name of the field')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$asJson = (bool) $input->getOption('json');
		$name = $input->getOption('name') ?: $input->getArgument('name');

		if (!$name && !$asJson) {
			$name = $this->searchField('Search for a field to view info');
			if ($name === '') {
				return Command::SUCCESS;
			}
		}

		if (!$name) {
			$msg = 'Provide field name via argument or --name.';
			return $asJson ? $this->outputJsonError($output, 'MISSING_NAME', $msg) : (error($msg) ?? Command::FAILURE);
		}

		$field = wire('fields')->get((string) $name);

		if (!$field || !$field->id) {
			$msg = "Field '{$name}' not found.";
			return $asJson ? $this->outputJsonError($output, 'NOT_FOUND', $msg) : (error($msg) ?? Command::FAILURE);
		}

		$templates = array_values(array_map(static fn(object $t): string => (string) $t->name, iterator_to_array($field->getTemplates())));
		$tableName = $field->getTable();
		$columns = [];
		try {
			$db = wire('database');
			$safeTableName = $db->escapeTable($tableName);
			$query = $db->prepare("SHOW COLUMNS FROM `{$safeTableName}`");
			$query->execute();
			$tableFields = $query->fetchAll(\PDO::FETCH_ASSOC);
			foreach ($tableFields as $col) {
				$columns[] = ['column' => $col['Field'], 'type' => $col['Type']];
			}
		} catch (\Throwable $e) {
			if (!$asJson) {
				warning('Could not fetch table structure: ' . $e->getMessage());
			}
		}

		$data = $field->getArray();
		$settings = [];
		foreach ($data as $k => $v) {
			if (is_array($v)) {
				$v = 'Array(...)';
			}
			if (is_object($v)) {
				$v = get_class($v);
			}
			$settings[$k] = (string) $v;
		}

		if ($asJson) {
			return $this->outputJsonSuccess($output, [
				'id' => $field->id,
				'name' => $field->name,
				'label' => (string) ($field->label ?: ''),
				'type' => $field->type->className(),
				'table' => $tableName,
				'flags' => $this->getFlagSummary($field->flags),
				'templates' => $templates,
				'columns' => $columns,
				'settings' => $settings,
			]);
		}

		info("Field Technical Insights: {$field->name}");

		$general = [
			['ID', (string) $field->id],
			['Name', $field->name],
			['Label', $field->label ?: '-'],
			['Type', $field->type->className()],
			['Table', $tableName],
			['Flags', $this->getFlagSummary($field->flags)],
		];

		table(
			headers: ['Property', 'Value'],
			rows: $general
		);

		info('Template Usage (' . count($templates) . ')');
		if ($templates) {
			info(implode(', ', $templates));
		} else {
			warning('Not used in any templates');
		}

		if ($columns) {
			info('Data Structure');
			$columnsData = array_map(static fn(array $col): array => [(string) $col['column'], (string) $col['type']], $columns);
			table(
				headers: ['Column', 'DataType'],
				rows: $columnsData
			);
		}

		info('Settings (Non-Default)');
		$settingsData = [];
		foreach ($settings as $k => $v) {
			if (strlen($v) > 100) {
				$v = substr($v, 0, 97) . '...';
			}
			$settingsData[] = [$k, $v];
		}

		$slicedSettings = array_slice($settingsData, 0, 15);
		if ($slicedSettings) {
			table(['Setting', 'Value'], $slicedSettings);
		}

		if (count($settingsData) > 15) {
			info('... and ' . (count($settingsData) - 15) . ' more.');
		}

		return Command::SUCCESS;
	}

	private function getFlagSummary(int $flags): string
	{
		$summary = [];
		if ($flags & Field::flagSystem) {
			$summary[] = 'System';
		}
		if ($flags & Field::flagPermanent) {
			$summary[] = 'Permanent';
		}
		if ($flags & Field::flagGlobal) {
			$summary[] = 'Global';
		}
		if ($flags & Field::flagAutojoin) {
			$summary[] = 'Autojoin';
		}

		return $summary ? implode(' | ', $summary) : 'None';
	}
}
