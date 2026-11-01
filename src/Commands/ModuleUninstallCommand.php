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

final class ModuleUninstallCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('module:uninstall')
			->setDescription('Uninstall a ProcessWire module by class name.')
			->addOption('name', null, InputOption::VALUE_OPTIONAL, 'Module class name')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write changes')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output')
			->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip interactive confirmations');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$name = $input->getOption('name') ? (string) $input->getOption('name') : '';
		$dryRun = (bool) $input->getOption('dry-run');
		$asJson = (bool) $input->getOption('json');
		$force = (bool) $input->getOption('force');

		if (!$name && !$asJson) {
			$name = $this->searchInstalledModule('Select a module to uninstall');
			if (empty($name)) {
				return Command::SUCCESS;
			}
		}

		if (!$name) {
			$msg = 'Provide --name.';
			return $asJson ? $this->outputJsonError($output, 'MISSING_NAME', $msg) : (error($msg) ?? Command::FAILURE);
		}

		$modules = \ProcessWire\wire('modules');
		$installed = $modules->isInstalled($name);
		if (!$installed) {
			if ($asJson) {
				return $this->outputJsonSuccess($output, ['name' => $name, 'alreadyUninstalled' => true]);
			}
			warning("Module '{$name}' is not installed.");
			return Command::SUCCESS;
		}

		if (!$force && !$dryRun) {
			if ($asJson) {
				return $this->outputJsonError($output, 'CONFIRMATION_REQUIRED', 'Use --force to run module:uninstall non-interactively with --json.');
			}
			if (!confirm("Uninstall module '{$name}'?", false)) {
				note('Aborted.');
				return Command::SUCCESS;
			}
		}

		$result = ['name' => $name, 'dryRun' => $dryRun];
		if ($dryRun) {
			if ($asJson) {
				return $this->outputJsonSuccess($output, $result);
			}
			note("Dry-run: would uninstall module '{$name}'.");
			return Command::SUCCESS;
		}

		try {
			if (!$asJson) {
				info("Uninstalling module '{$name}'...");
			}
			$modules->uninstall($name);
		} catch (\Throwable $e) {
			$msg = "Uninstall failed for module '{$name}': " . $e->getMessage();
			return $asJson ? $this->outputJsonError($output, 'UNINSTALL_FAILED', $msg) : (error($msg) ?? Command::FAILURE);
		}

		if ($asJson) {
			return $this->outputJsonSuccess($output, $result + ['uninstalled' => true]);
		}

		info("Uninstalled module '{$name}'.");
		return Command::SUCCESS;
	}
}
