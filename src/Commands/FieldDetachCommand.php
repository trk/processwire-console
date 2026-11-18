<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Traits\InteractWithProcessWire;
use function Laravel\Prompts\confirm;
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
		$dryRun = (bool) $input->getOption('dry-run');
		$force = (bool) $input->getOption('force');

		if (!$fieldName && $this->canPrompt($input, $asJson, $force)) {
			$fieldName = $this->searchField('Select the field to detach');
			if ($fieldName === '') {
				return Command::SUCCESS;
			}
		}

		$templateName = $input->getOption('template');
		if (!$templateName && $this->canPrompt($input, $asJson, $force)) {
			$templateName = $this->searchTemplate('Select the template to detach from');
			if ($templateName === '') {
				return Command::SUCCESS;
			}
		}

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

		if ((((int) $template->flags) & \ProcessWire\Template::flagSystem) !== 0) {
			return $this->failCommand($output, $asJson, 'SYSTEM_TEMPLATE', "Template '{$templateName}' is a system template and its core fields cannot be detached via CLI.");
		}
		if ((((int) $field->flags) & \ProcessWire\Field::flagSystem) !== 0) {
			return $this->failCommand($output, $asJson, 'SYSTEM_FIELD', "Field '{$fieldName}' is a system field and cannot be detached.");
		}
		if ((((int) $field->flags) & \ProcessWire\Field::flagPermanent) !== 0) {
			return $this->failCommand($output, $asJson, 'PERMANENT_FIELD', "Field '{$fieldName}' is marked permanent and cannot be detached.");
		}
		if ((((int) $field->flags) & \ProcessWire\Field::flagGlobal) !== 0 && empty($template->noGlobal)) {
			return $this->failCommand($output, $asJson, 'GLOBAL_FIELD', "Field '{$fieldName}' is a global field required by template '{$templateName}' and cannot be detached.");
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
			if ($asJson || !$input->isInteractive()) {
				return $this->failCommand($output, $asJson, 'CONFIRMATION_REQUIRED', 'Use --force to run field:detach non-interactively.');
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

		try {
			$fg->remove($field);
			$fg->save();
		} catch (\Throwable $e) {
			return $this->failCommand($output, $asJson, 'DETACH_FAILED', $e->getMessage());
		}

		if ($asJson) {
			return $this->outputJsonSuccess($output, $result + ['detached' => true]);
		}

		info("Detached field '{$fieldName}' from template '{$templateName}'.");
		return Command::SUCCESS;
	}
}
