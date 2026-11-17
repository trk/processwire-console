<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Traits\InteractWithProcessWire;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;

final class FieldDeleteCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('field:delete')
			->setDescription('Delete a field.')
			->addArgument('name', InputArgument::OPTIONAL, 'The name of the field to delete')
			->addOption('name', null, InputOption::VALUE_OPTIONAL, 'The name of the field to delete')
			->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip interactive confirmations')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write changes')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$asJson = (bool) $input->getOption('json');
		$force = (bool) $input->getOption('force');
		$dryRun = (bool) $input->getOption('dry-run');
		$name = $input->getOption('name') ?: $input->getArgument('name');

		if (!$name && $this->canPrompt($input, $asJson, $force)) {
			$name = $this->searchField('Search for a field to delete');
			if ($name === '') {
				return Command::SUCCESS;
			}
		}

		if (!$name) {
			return $this->failCommand($output, $asJson, 'MISSING_NAME', 'Provide field name via argument or --name.');
		}

		if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', (string) $name) !== 1) {
			return $this->failCommand($output, $asJson, 'INVALID_NAME', "Invalid field name: '{$name}'.");
		}

		$field = \ProcessWire\wire('fields')->get((string) $name);
		if (!$field || !$field->id) {
			return $this->failCommand($output, $asJson, 'NOT_FOUND', "Field '{$name}' not found.");
		}

		$protectedFlags = \ProcessWire\Field::flagSystem | \ProcessWire\Field::flagPermanent | \ProcessWire\Field::flagGlobal;
		if ((((int) $field->flags) & $protectedFlags) !== 0 || (string) $field->name === 'title') {
			return $this->failCommand($output, $asJson, 'SYSTEM_FIELD', "Field '{$name}' is a protected core/global field and cannot be deleted.");
		}

		$fieldgroups = $field->getFieldgroups();
		if ($fieldgroups->count() > 0) {
			$attachedNames = $fieldgroups->implode(', ', 'name');
			return $this->failCommand(
				$output,
				$asJson,
				'FIELD_IN_USE',
				"Field '{$name}' is still attached to template(s): {$attachedNames}. Detach it first."
			);
		}

		$result = ['name' => (string) $field->name, 'dryRun' => $dryRun];
		if ($dryRun) {
			if ($asJson) {
				return $this->outputJsonSuccess($output, $result);
			}
			note("Dry-run: would delete field '{$field->name}'.");
			return Command::SUCCESS;
		}

		if (!$force && (!$input->isInteractive() || $asJson)) {
			return $this->failCommand($output, $asJson, 'CONFIRMATION_REQUIRED', "Deleting field '{$field->name}' in non-interactive or JSON mode requires --force.");
		}

		if ($force || confirm("Are you sure you want to delete field '{$field->name}'? This will delete all data associated with it.", default: false)) {
			try {
				\ProcessWire\wire('fields')->delete($field);
			} catch (\Throwable $e) {
				return $this->failCommand($output, $asJson, 'DELETE_FAILED', $e->getMessage());
			}
			if ($asJson) {
				return $this->outputJsonSuccess($output, $result + ['deleted' => true]);
			}
			info("Field '{$field->name}' deleted.");
			return Command::SUCCESS;
		}

		note('Aborted.');
		return Command::SUCCESS;
	}
}
