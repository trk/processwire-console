<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Traits\InteractWithProcessWire;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\warning;

final class FieldDetachCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('field:detach')
			->setDescription('Detach a field from a template.')
			->addOption('field', null, InputOption::VALUE_OPTIONAL, 'Field name')
			->addOption('template', null, InputOption::VALUE_OPTIONAL, 'Template name')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write changes')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output')
			->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip interactive confirmations');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$fieldName = $input->getOption('field');
		$asJson = (bool) $input->getOption('json');

		if (!$fieldName && !$asJson) {
			$fieldName = $this->searchField('Select the field to detach');
			if ($fieldName === '') {
				return Command::SUCCESS;
			}
		}

		$templateName = $input->getOption('template');
		if (!$templateName && !$asJson) {
			$templateName = $this->searchTemplate('Select the template to detach from');
			if ($templateName === '') {
				return Command::SUCCESS;
			}
		}

		$dryRun = (bool) $input->getOption('dry-run');
		$force = (bool) $input->getOption('force');

		if (!$fieldName || !$templateName) {
			$msg = 'Provide --field and --template.';
			return $asJson ? $this->outputJsonError($output, 'MISSING_OPTIONS', $msg) : (error($msg) ?? Command::FAILURE);
		}

		$fields = \ProcessWire\wire('fields');
		$templates = \ProcessWire\wire('templates');
		$field = $fields->get((string) $fieldName);
		$template = $templates->get((string) $templateName);
		if (!$field || !$field->id) {
			$msg = "Field not found: {$fieldName}";
			return $asJson ? $this->outputJsonError($output, 'NOT_FOUND', $msg) : (error($msg) ?? Command::FAILURE);
		}
		if (!$template || !$template->id) {
			$msg = "Template not found: {$templateName}";
			return $asJson ? $this->outputJsonError($output, 'NOT_FOUND', $msg) : (error($msg) ?? Command::FAILURE);
		}

		$fg = $template->fieldgroup;
		$attached = (bool) $fg->getField((string) $fieldName);
		if (!$attached) {
			if ($asJson) {
				return $this->outputJsonSuccess($output, ['field' => $fieldName, 'template' => $templateName, 'detached' => false]);
			}
			warning("Field '{$fieldName}' is not attached to template '{$templateName}'.");
			return Command::SUCCESS;
		}

		if (!$force && !$dryRun) {
			if ($asJson) {
				return $this->outputJsonError($output, 'CONFIRMATION_REQUIRED', 'Use --force to run field:detach non-interactively with --json.');
			}
			if (!confirm("Detach field '{$fieldName}' from template '{$templateName}'?", default: false)) {
				note('Aborted.');
				return Command::SUCCESS;
			}
		}

		$result = ['field' => $fieldName, 'template' => $templateName, 'dryRun' => $dryRun];
		if ($dryRun) {
			if ($asJson) {
				return $this->outputJsonSuccess($output, $result);
			}
			note("Dry-run: would detach field '{$fieldName}' from template '{$templateName}'.");
			return Command::SUCCESS;
		}

		$fg->remove($field);
		$fg->save();

		if ($asJson) {
			return $this->outputJsonSuccess($output, $result + ['detached' => true]);
		}

		info("Detached field '{$fieldName}' from template '{$templateName}'.");
		return Command::SUCCESS;
	}
}
