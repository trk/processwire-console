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
		$name = $input->getOption('name') ?: $input->getArgument('name');
		$dryRun = (bool) $input->getOption('dry-run');
		$asJson = (bool) $input->getOption('json');
		$force = (bool) $input->getOption('force');

		if (!$name && !$asJson) {
			$name = $this->searchTemplate('Select the template to delete');
			if ($name === '') {
				return Command::SUCCESS;
			}
		}

		if (!$name) {
			$msg = 'Provide template name via argument or --name.';
			return $asJson ? $this->outputJsonError($output, 'MISSING_NAME', $msg) : (error($msg) ?? Command::FAILURE);
		}

		$templates = \ProcessWire\wire('templates');
		$template = $templates->get((string) $name);

		if (!$template || !$template->id) {
			$msg = "Template '{$name}' not found.";
			return $asJson ? $this->outputJsonError($output, 'NOT_FOUND', $msg) : (error($msg) ?? Command::FAILURE);
		}

		if ($template->flags & \ProcessWire\Template::flagSystem) {
			$msg = "Template '{$name}' is a system template and cannot be deleted.";
			return $asJson ? $this->outputJsonError($output, 'SYSTEM_TEMPLATE', $msg) : (error($msg) ?? Command::FAILURE);
		}

		$result = ['name' => $name, 'dryRun' => $dryRun];
		if ($dryRun) {
			if ($asJson) {
				return $this->outputJsonSuccess($output, $result);
			}
			note("Dry-run: would delete template '{$name}' and its associated fieldgroup.");
			return Command::SUCCESS;
		}

		if (!$force && (!$input->isInteractive() || $asJson)) {
			$msg = "Deleting template '{$name}' in non-interactive or JSON mode requires --force.";
			return $asJson ? $this->outputJsonError($output, 'CONFIRMATION_REQUIRED', $msg) : (error($msg) ?? Command::FAILURE);
		}

		if ($force || confirm("Are you sure you want to delete template '{$name}' and its associated fieldgroup?", default: false)) {
			try {
				$fg = $template->fieldgroup;
				$templates->delete($template);
				if ($fg && $fg->id) {
					\ProcessWire\wire('fieldgroups')->delete($fg);
				}

				if ($asJson) {
					return $this->outputJsonSuccess($output, $result + ['deleted' => true]);
				}

				info("Template '{$name}' deleted.");
				return Command::SUCCESS;
			} catch (\Throwable $e) {
				$msg = 'Error deleting template: ' . $e->getMessage();
				return $asJson ? $this->outputJsonError($output, 'DELETE_FAILED', $msg) : (error($msg) ?? Command::FAILURE);
			}
		}

		note('Aborted.');
		return Command::SUCCESS;
	}
}
