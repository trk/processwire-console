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

final class TemplateRenameCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('template:rename')
			->setDescription('Rename a template.')
			->addOption('name', null, InputOption::VALUE_OPTIONAL, 'Current template name')
			->addOption('new-name', null, InputOption::VALUE_OPTIONAL, 'New template name')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write changes')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$old = $input->getOption('name');
		$new = $input->getOption('new-name');
		$asJson = (bool) $input->getOption('json');

		if (!$old && $this->canPrompt($input, $asJson)) {
			$old = $this->searchTemplate('Select the template to rename');
			if ($old === '') {
				return Command::SUCCESS;
			}
		}

		if (!$new && $this->canPrompt($input, $asJson)) {
			$new = text(
				label: 'Enter the new template name',
				required: true,
				validate: static fn(string $value): ?string => preg_match('/^[a-zA-Z0-9_-]+$/', $value) ? null : 'Invalid template name format'
			);
		}

		$dryRun = (bool) $input->getOption('dry-run');

		if (!$old || !$new) {
			return $this->failCommand($output, $asJson, 'MISSING_OPTIONS', 'Provide --name and --new-name.');
		}

		if (!preg_match('/^[a-zA-Z0-9_-]+$/', (string) $old) || !preg_match('/^[a-zA-Z0-9_-]+$/', (string) $new) || ctype_digit((string) $new)) {
			return $this->failCommand($output, $asJson, 'INVALID_NAME', "Invalid template name format: '{$new}'.");
		}

		$templates = \ProcessWire\wire('templates');
		$fieldgroups = \ProcessWire\wire('fieldgroups');
		$t = $templates->get((string) $old);
		if (!$t || !$t->id) {
			return $this->failCommand($output, $asJson, 'NOT_FOUND', "Template not found: {$old}");
		}
		if ($t->flags & \ProcessWire\Template::flagSystem) {
			return $this->failCommand($output, $asJson, 'SYSTEM_TEMPLATE', "Template '{$old}' is a system template and cannot be renamed.");
		}

		$existingNew = $templates->get((string) $new);
		if ($existingNew && $existingNew->id) {
			return $this->failCommand($output, $asJson, 'ALREADY_EXISTS', "A template named '{$new}' already exists.");
		}

		$existingFg = $fieldgroups ? $fieldgroups->get((string) $new) : null;
		if ($existingFg && $existingFg->id && (!$t->fieldgroup || (int) $existingFg->id !== (int) $t->fieldgroup->id)) {
			return $this->failCommand($output, $asJson, 'ALREADY_EXISTS', "A fieldgroup named '{$new}' already exists.");
		}

		$result = ['old' => $old, 'new' => $new, 'dryRun' => $dryRun];
		if ($dryRun) {
			if ($asJson) {
				return $this->outputJsonSuccess($output, $result);
			}
			note("Dry-run: would rename template '{$old}' to '{$new}'.");
			return Command::SUCCESS;
		}

		try {
			$fg = $t->fieldgroup;
			if ($fg && $fg->id && (string) $fg->name === (string) $old) {
				$fgShared = false;
				foreach ($templates as $otherTpl) {
					if ((int) $otherTpl->id === (int) $t->id) {
						continue;
					}
					if ($otherTpl->fieldgroup && (int) $otherTpl->fieldgroup->id === (int) $fg->id) {
						$fgShared = true;
						break;
					}
				}
				if (!$fgShared && $fieldgroups) {
					$fg->name = (string) $new;
					$fieldgroups->save($fg);
				}
			}

			$t->name = (string) $new;
			$templates->save($t);
		} catch (\Throwable $e) {
			return $this->failCommand($output, $asJson, 'SAVE_FAILED', $e->getMessage());
		}

		if ($asJson) {
			return $this->outputJsonSuccess($output, $result + ['renamed' => true]);
		}

		info("Renamed template '{$old}' to '{$new}'.");
		return Command::SUCCESS;
	}
}
