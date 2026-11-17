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
use function Laravel\Prompts\select;

final class FieldAttachCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('field:attach')
			->setDescription('Attach a field to a template with optional position.')
			->addOption('field', null, InputOption::VALUE_OPTIONAL, 'Field name')
			->addOption('template', null, InputOption::VALUE_OPTIONAL, 'Template name')
			->addOption('after', null, InputOption::VALUE_OPTIONAL, 'Attach after this field name')
			->addOption('position', null, InputOption::VALUE_OPTIONAL, 'Position: first|last|after=FIELD|before=FIELD')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write changes')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$fieldName = $input->getOption('field');
		$asJson = (bool) $input->getOption('json');

		if (!$fieldName && $this->canPrompt($input, $asJson)) {
			$fieldName = $this->searchField('Select the field to attach');
			if ($fieldName === '') {
				return Command::SUCCESS;
			}
		}

		$templateName = $input->getOption('template');
		if (!$templateName && $this->canPrompt($input, $asJson)) {
			$templateName = $this->searchTemplate('Select the template to attach to');
			if ($templateName === '') {
				return Command::SUCCESS;
			}
		}

		$afterOpt = $input->getOption('after');
		$position = $input->getOption('position');
		if ($afterOpt) {
			$position = 'after=' . $afterOpt;
		} elseif (!$position && $this->canPrompt($input, $asJson)) {
			$position = (string) select(
				label: 'Where should this field be placed?',
				options: ['last' => 'At the end', 'first' => 'At the beginning'],
				default: 'last'
			);
		} elseif (!$position) {
			$position = 'last';
		}

		$dryRun = (bool) $input->getOption('dry-run');

		if (!$fieldName || !$templateName) {
			return $this->failCommand($output, $asJson, 'MISSING_OPTIONS', 'Provide --field and --template.');
		}

		if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', (string) $fieldName) !== 1) {
			return $this->failCommand($output, $asJson, 'INVALID_FIELD', "Invalid field name: {$fieldName}");
		}
		if (preg_match('/^[A-Za-z0-9_-]+$/', (string) $templateName) !== 1) {
			return $this->failCommand($output, $asJson, 'INVALID_TEMPLATE', "Invalid template name: {$templateName}");
		}

		$fields = \ProcessWire\wire('fields');
		$templates = \ProcessWire\wire('templates');
		$field = $fields->get((string) $fieldName);
		$template = $templates->get((string) $templateName);
		if (!$field || !$field->id) {
			return $this->failCommand($output, $asJson, 'NOT_FOUND', "Field not found: {$fieldName}");
		}
		if (!$template || !$template->id) {
			return $this->failCommand($output, $asJson, 'NOT_FOUND', "Template not found: {$templateName}");
		}

		$fg = $template->fieldgroup;
		$exists = (bool) $fg->getField((string) $fieldName);

		$result = ['field' => $fieldName, 'template' => $templateName, 'position' => $position, 'alreadyAttached' => $exists, 'dryRun' => $dryRun];
		if ($dryRun) {
			if ($asJson) {
				return $this->outputJsonSuccess($output, $result);
			}
			note("Dry-run: would attach field '{$fieldName}' to template '{$templateName}' at '{$position}'.");
			return Command::SUCCESS;
		}

		if (!$exists) {
			$fg->add($field);
		}

		if (str_starts_with((string) $position, 'after=')) {
			$afterName = substr((string) $position, 6);
			$afterField = $fg->getField($afterName);
			if ($afterField) {
				$fg->insertAfter($field, $afterField);
			}
		} elseif (str_starts_with((string) $position, 'before=')) {
			$beforeName = substr((string) $position, 7);
			$beforeField = $fg->getField($beforeName);
			if ($beforeField) {
				$fg->insertBefore($field, $beforeField);
			}
		} elseif ($position === 'first') {
			$first = $fg->first();
			if ($first && $first !== $field) {
				$fg->insertBefore($field, $first);
			}
		}

		$fg->save();

		if ($asJson) {
			return $this->outputJsonSuccess($output, $result + ['attached' => true]);
		}

		info("Attached field '{$fieldName}' to template '{$templateName}'.");
		return Command::SUCCESS;
	}
}
