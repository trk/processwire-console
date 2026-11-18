<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Traits\InteractWithProcessWire;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

final class FieldUpdateCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('field:update')
			->setDescription('Update field settings (label, tags, etc.).')
			->addOption('name', null, InputOption::VALUE_OPTIONAL, 'Field name')
			->addOption('set', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Set field property as key=value (repeatable)')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write changes')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$name = $input->getOption('name');
		$asJson = (bool) $input->getOption('json');

		if (!$name && $this->canPrompt($input, $asJson)) {
			$name = $this->searchField('Select the field to update');
			if ($name === '') {
				return Command::SUCCESS;
			}
		}

		if (!$name) {
			return $this->failCommand($output, $asJson, 'MISSING_NAME', 'Provide --name.');
		}

		if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', (string) $name) !== 1) {
			return $this->failCommand($output, $asJson, 'INVALID_NAME', "Invalid field name: '{$name}'.");
		}

		$setRaw = $input->getOption('set');
		$changes = $this->parseKeyValueSets($setRaw);

		if ($changes === [] && $this->canPrompt($input, $asJson)) {
			$prompted = text(
				label: 'Enter key=value pair to set',
				placeholder: 'label=Body',
				required: true
			);
			$changes = $this->parseKeyValueSets($prompted);
		}
		unset(
			$changes['id'],
			$changes['name'],
			$changes['flags'],
			$changes['flagsAdd'],
			$changes['flagsDel'],
			$changes['type'],
			$changes['prevTable'],
			$changes['prevName'],
			$changes['prevFieldtype']
		);

		$dryRun = (bool) $input->getOption('dry-run');

		$field = \ProcessWire\wire('fields')->get((string) $name);
		if (!$field || !$field->id) {
			return $this->failCommand($output, $asJson, 'NOT_FOUND', "Field not found: {$name}");
		}

		if ((((int) $field->flags) & \ProcessWire\Field::flagSystem) !== 0) {
			return $this->failCommand($output, $asJson, 'SYSTEM_FIELD', "Field '{$name}' is a system field and cannot be modified via CLI.");
		}

		if ($changes === []) {
			return $this->failCommand($output, $asJson, 'MISSING_CHANGES', 'No valid key=value pairs provided.');
		}

		$result = ['name' => $name, 'changes' => $changes, 'dryRun' => $dryRun];
		if ($dryRun) {
			if ($asJson) {
				return $this->outputJsonSuccess($output, $result);
			}
			note("Dry-run: would update field '{$name}' with: " . json_encode($changes));
			return Command::SUCCESS;
		}

		foreach ($changes as $k => $v) {
			try {
				$field->set($k, $v);
			} catch (\Throwable $e) {
				if (!$asJson) {
					warning("Property '{$k}': " . $e->getMessage());
				}
			}
		}
		try {
			\ProcessWire\wire('fields')->save($field);
		} catch (\Throwable $e) {
			return $this->failCommand($output, $asJson, 'SAVE_FAILED', $e->getMessage());
		}

		if ($asJson) {
			return $this->outputJsonSuccess($output, $result + ['saved' => true]);
		}

		info("Updated field '{$name}'.");
		return Command::SUCCESS;
	}
}
