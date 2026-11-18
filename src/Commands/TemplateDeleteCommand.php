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

final class TemplateDeleteCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('template:delete')
			->setDescription('Delete a template.')
			->addArgument('name', InputArgument::OPTIONAL, 'The name of the template to delete')
			->addOption('name', null, InputOption::VALUE_OPTIONAL, 'The name of the template to delete')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write changes')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output')
			->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip interactive confirmations');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$rawOptName = $input->getOption('name');
		$rawArgName = $input->getArgument('name');
		$name = ($rawOptName !== null && $rawOptName !== '')
			? (string) $rawOptName
			: (($rawArgName !== null && $rawArgName !== '') ? (string) $rawArgName : '');
		$dryRun = (bool) $input->getOption('dry-run');
		$asJson = (bool) $input->getOption('json');
		$force = (bool) $input->getOption('force');

		if ($name === '' && $this->canPrompt($input, $asJson, $force)) {
			$name = $this->searchTemplate('Select the template to delete');
			if ($name === '') {
				return Command::SUCCESS;
			}
		}

		if ($name === '') {
			return $this->failCommand($output, $asJson, 'MISSING_NAME', 'Provide template name via argument or --name.');
		}

		if (preg_match('/^[A-Za-z0-9_-]+$/', $name) !== 1 || ctype_digit($name)) {
			return $this->failCommand($output, $asJson, 'INVALID_NAME', "Invalid template name: '{$name}'.");
		}

		$templates = \ProcessWire\wire('templates');
		$template = $templates->get((string) $name);

		if (!$template || !$template->id) {
			return $this->failCommand($output, $asJson, 'NOT_FOUND', "Template '{$name}' not found.");
		}

		if ($template->flags & \ProcessWire\Template::flagSystem) {
			return $this->failCommand($output, $asJson, 'SYSTEM_TEMPLATE', "Template '{$name}' is a system template and cannot be deleted.");
		}

		$safeTplSelector = \ProcessWire\wire('sanitizer')->selectorValue((string) $template->name);
		$pageCount = (int) \ProcessWire\wire('pages')->count("template={$safeTplSelector}, include=all");
		if ($pageCount > 0) {
			return $this->failCommand(
				$output,
				$asJson,
				'TEMPLATE_IN_USE',
				"Template '{$template->name}' cannot be deleted because {$pageCount} page(s) still use it."
			);
		}

		$result = ['name' => (string) $template->name, 'dryRun' => $dryRun];
		if ($dryRun) {
			if ($asJson) {
				return $this->outputJsonSuccess($output, $result);
			}
			note("Dry-run: would delete template '{$template->name}' and its associated fieldgroup.");
			return Command::SUCCESS;
		}

		if (!$force && (!$input->isInteractive() || $asJson)) {
			return $this->failCommand($output, $asJson, 'CONFIRMATION_REQUIRED', "Deleting template '{$template->name}' in non-interactive or JSON mode requires --force.");
		}

		if ($force || confirm("Are you sure you want to delete template '{$template->name}' and its associated fieldgroup?", default: false)) {
			try {
				$fg = $template->fieldgroup;
				$templates->delete($template);
				if ($fg && $fg->id) {
					$sharedCount = 0;
					foreach ($templates as $otherTpl) {
						if (
							($otherTpl->fieldgroup && (int) $otherTpl->fieldgroup->id === (int) $fg->id)
							|| (int) $otherTpl->fieldgroups_id === (int) $fg->id
						) {
							$sharedCount++;
						}
					}
					if ($sharedCount === 0) {
						\ProcessWire\wire('fieldgroups')->delete($fg);
					}
				}

				if ($asJson) {
					return $this->outputJsonSuccess($output, $result + ['deleted' => true]);
				}

				info("Template '{$result['name']}' deleted.");
				return Command::SUCCESS;
			} catch (\Throwable $e) {
				return $this->failCommand($output, $asJson, 'DELETE_FAILED', 'Error deleting template: ' . $e->getMessage());
			}
		}

		note('Aborted.');
		return Command::SUCCESS;
	}
}
