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
		$name = $input->getOption('name') ? trim((string) $input->getOption('name')) : '';
		$dryRun = (bool) $input->getOption('dry-run');
		$asJson = (bool) $input->getOption('json');
		$force = (bool) $input->getOption('force');

		if ($name === '' && $this->canPrompt($input, $asJson, $force)) {
			$name = $this->searchInstalledModule('Select a module to uninstall');
			if ($name === '') {
				return Command::SUCCESS;
			}
		}

		if ($name === '') {
			return $this->failCommand($output, $asJson, 'MISSING_NAME', 'Provide --name.');
		}

		if (!$this->isValidIdentifier($name)) {
			return $this->failCommand($output, $asJson, 'INVALID_NAME', "Invalid module class name '{$name}'.");
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

		$info = $modules->getModuleInfoVerbose($name);
		if (!empty($info['permanent'])) {
			return $this->failCommand($output, $asJson, 'PERMANENT_MODULE', "Module '{$name}' is marked permanent and cannot be uninstalled.");
		}

		$uninstallReason = $modules->isUninstallable($name, true);
		if ($uninstallReason !== true) {
			$reasonText = is_string($uninstallReason) && $uninstallReason !== ''
				? $uninstallReason
				: "Module '{$name}' cannot be uninstalled.";
			$errorCode = str_contains(strtolower($reasonText), 'required by') ? 'MODULE_REQUIRED_BY' : 'NOT_UNINSTALLABLE';

			return $this->failCommand(
				$output,
				$asJson,
				$errorCode,
				"Module '{$name}' cannot be uninstalled: {$reasonText}"
			);
		}

		if (!$force && !$dryRun) {
			if ($asJson || !$input->isInteractive()) {
				return $this->failCommand($output, $asJson, 'CONFIRMATION_REQUIRED', 'Use --force to run module:uninstall non-interactively.');
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
			$ok = $modules->uninstall($name);
			if ($ok === false || $modules->isInstalled($name)) {
				return $this->failCommand($output, $asJson, 'UNINSTALL_FAILED', "ProcessWire refused to uninstall module '{$name}'.");
			}
		} catch (\Throwable $e) {
			return $this->failCommand($output, $asJson, 'UNINSTALL_FAILED', "Uninstall failed for module '{$name}': " . $e->getMessage());
		}

		if ($asJson) {
			return $this->outputJsonSuccess($output, $result + ['uninstalled' => true]);
		}

		info("Uninstalled module '{$name}'.");
		return Command::SUCCESS;
	}
}
