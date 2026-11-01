<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Traits\InteractWithProcessWire;
use function Laravel\Prompts\error;
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
			->setDescription('Update a field settings (label, tags, etc.).')
			->addOption('name', null, InputOption::VALUE_OPTIONAL, 'Field name')
			->addOption('set', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Set field property as key=value (repeatable or comma-separated)')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write changes')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$name = $input->getOption('name');
		$asJson = (bool) $input->getOption('json');

		if (!$name && !$asJson) {
			$name = $this->searchField('Select the field to update');
			if ($name === '') {
				return Command::SUCCESS;
			}
		}

		if (!$name) {
			$msg = 'Provide --name.';
			return $asJson ? $this->outputJsonError($output, 'MISSING_NAME', $msg) : (error($msg) ?? Command::FAILURE);
		}

		$setRaw = $input->getOption('set');
		$changes = $this->parseKeyValueSets($setRaw);

		if (empty($changes) && !$asJson && $input->isInteractive()) {
			$prompted = text(
				label: 'Enter key=value pairs to set',
				placeholder: 'label=Body,tags=content',
				required: true
			);
			$changes = $this->parseKeyValueSets($prompted);
		}

		$dryRun = (bool) $input->getOption('dry-run');

		$field = \ProcessWire\wire('fields')->get((string) $name);
		if (!$field || !$field->id) {
			$msg = "Field not found: {$name}";
			return $asJson ? $this->outputJsonError($output, 'NOT_FOUND', $msg) : (error($msg) ?? Command::FAILURE);
		}

		if (empty($changes)) {
			$msg = 'No valid key=value pairs provided.';
			return $asJson ? $this->outputJsonError($output, 'MISSING_CHANGES', $msg) : (error($msg) ?? Command::FAILURE);
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
		$field->save();

		if ($asJson) {
			return $this->outputJsonSuccess($output, $result + ['saved' => true]);
		}

		info("Updated field '{$name}'.");
		return Command::SUCCESS;
	}
}
