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

final class FieldRenameCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('field:rename')
			->setDescription('Rename a field.')
			->addOption('name', null, InputOption::VALUE_OPTIONAL, 'Current field name')
			->addOption('new-name', null, InputOption::VALUE_OPTIONAL, 'New field name')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write changes')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$old = $input->getOption('name');
		$new = $input->getOption('new-name');
		$asJson = (bool) $input->getOption('json');

		if (!$old && $this->canPrompt($input, $asJson)) {
			$old = $this->searchField('Select the field to rename');
			if ($old === '') {
				return Command::SUCCESS;
			}
		}

		if (!$new && $this->canPrompt($input, $asJson)) {
			$new = text(
				label: 'Enter the new field name',
				required: true,
				validate: static fn(string $value): ?string => preg_match('/^[a-z_][a-z0-9_]*$/', $value) ? null : 'Invalid field name format'
			);
		}

		$dryRun = (bool) $input->getOption('dry-run');

		if (!$old || !$new) {
			return $this->failCommand($output, $asJson, 'MISSING_OPTIONS', 'Provide --name and --new-name.');
		}

		if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', (string) $old) !== 1 || preg_match('/^[a-z_][a-z0-9_]*$/', (string) $new) !== 1) {
			return $this->failCommand($output, $asJson, 'INVALID_NAME', "Invalid field name format: '{$new}'. Must match /^[a-z_][a-z0-9_]*$/.");
		}

		$fields = \ProcessWire\wire('fields');
		$f = $fields->get((string) $old);
		if (!$f || !$f->id) {
			return $this->failCommand($output, $asJson, 'NOT_FOUND', "Field not found: {$old}");
		}
		$protectedFlags = \ProcessWire\Field::flagSystem | \ProcessWire\Field::flagPermanent | \ProcessWire\Field::flagGlobal;
		if ((((int) $f->flags) & $protectedFlags) !== 0 || (string) $f->name === 'title') {
			return $this->failCommand($output, $asJson, 'SYSTEM_FIELD', "Field '{$old}' is a protected core/global field and cannot be renamed.");
		}

		$existingNew = $fields->get((string) $new);
		if ($existingNew && $existingNew->id) {
			return $this->failCommand($output, $asJson, 'ALREADY_EXISTS', "A field named '{$new}' already exists.");
		}

		$result = ['old' => $old, 'new' => $new, 'dryRun' => $dryRun];
		if ($dryRun) {
			if ($asJson) {
				return $this->outputJsonSuccess($output, $result);
			}
			note("Dry-run: would rename field '{$old}' to '{$new}'.");
			return Command::SUCCESS;
		}

		$f->name = (string) $new;
		$fields->save($f);

		if ($asJson) {
			return $this->outputJsonSuccess($output, $result + ['renamed' => true]);
		}

		info("Renamed field '{$old}' to '{$new}'.");
		return Command::SUCCESS;
	}
}
