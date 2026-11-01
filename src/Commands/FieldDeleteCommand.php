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
use function Laravel\Prompts\error;
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

		if (!$name && !$asJson) {
			$name = $this->searchField('Search for a field to delete');
			if ($name === '') {
				return Command::SUCCESS;
			}
		}

		if (!$name) {
			$msg = 'Provide field name via argument or --name.';
			return $asJson ? $this->outputJsonError($output, 'MISSING_NAME', $msg) : (error($msg) ?? Command::FAILURE);
		}

		$field = \ProcessWire\wire('fields')->get((string) $name);

		if (!$field || !$field->id) {
			$msg = "Field '{$name}' not found.";
			return $asJson ? $this->outputJsonError($output, 'NOT_FOUND', $msg) : (error($msg) ?? Command::FAILURE);
		}

		if ($field->flags & \ProcessWire\Field::flagSystem) {
			$msg = "Field '{$name}' is a system field and cannot be deleted.";
			return $asJson ? $this->outputJsonError($output, 'SYSTEM_FIELD', $msg) : (error($msg) ?? Command::FAILURE);
		}

		$result = ['name' => $name, 'dryRun' => $dryRun];
		if ($dryRun) {
			if ($asJson) {
				return $this->outputJsonSuccess($output, $result);
			}
			note("Dry-run: would delete field '{$name}'.");
			return Command::SUCCESS;
		}

		if (!$force && (!$input->isInteractive() || $asJson)) {
			$msg = "Deleting field '{$name}' in non-interactive or JSON mode requires --force.";
			return $asJson ? $this->outputJsonError($output, 'CONFIRMATION_REQUIRED', $msg) : (error($msg) ?? Command::FAILURE);
		}

		if ($force || confirm("Are you sure you want to delete field '{$name}'? This will delete all data associated with it.", false)) {
			\ProcessWire\wire('fields')->delete($field);
			if ($asJson) {
				return $this->outputJsonSuccess($output, $result + ['deleted' => true]);
			}
			info("Field '{$name}' deleted.");
			return Command::SUCCESS;
		}

		note('Aborted.');
		return Command::SUCCESS;
	}
}
