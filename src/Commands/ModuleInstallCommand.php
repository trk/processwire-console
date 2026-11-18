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

final class ModuleInstallCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('module:install')
			->setDescription('Install a ProcessWire module by class name.')
			->addOption('name', null, InputOption::VALUE_OPTIONAL, 'Module class name')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write changes')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$rawName = $input->getOption('name');
		$name = ($rawName !== null && $rawName !== '') ? trim((string) $rawName) : '';
		$dryRun = (bool) $input->getOption('dry-run');
		$asJson = (bool) $input->getOption('json');

		if ($rawName === null && $this->canPrompt($input, $asJson)) {
			$name = $this->searchInstallableModule('Select a module to install');
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
		if (!$installed && (!$modules->getModuleFile($name) || !$modules->isInstallable($name))) {
			$modules->refresh();
			$installed = $modules->isInstalled($name);
		}
		if (!$installed && !$modules->getModuleFile($name)) {
			return $this->failCommand($output, $asJson, 'NOT_FOUND', "Module not found: {$name}");
		}
		if (!$installed && !$modules->isInstallable($name)) {
			return $this->failCommand($output, $asJson, 'NOT_INSTALLABLE', "Module '{$name}' is not currently installable (check module requirements).");
		}

		$result = ['name' => $name, 'alreadyInstalled' => $installed, 'dryRun' => $dryRun];

		if ($dryRun) {
			if ($asJson) {
				return $this->outputJsonSuccess($output, $result);
			}
			note("Dry-run: would install module '{$name}'.");
			return Command::SUCCESS;
		}

		if ($installed) {
			if ($asJson) {
				return $this->outputJsonSuccess($output, $result);
			}
			info("Module '{$name}' is already installed.");
			return Command::SUCCESS;
		}

		try {
			if (!$asJson) {
				info("Installing module '{$name}'...");
			}
			$installedModule = $modules->install($name);
			if ($installedModule === null || !$modules->isInstalled($name)) {
				return $this->failCommand($output, $asJson, 'INSTALL_FAILED', "ProcessWire failed to install module '{$name}'.");
			}
		} catch (\Throwable $e) {
			return $this->failCommand($output, $asJson, 'INSTALL_FAILED', "Install failed for module '{$name}': " . $e->getMessage());
		}

		if ($asJson) {
			return $this->outputJsonSuccess($output, $result + ['installed' => true]);
		}

		info("Installed module '{$name}'.");
		return Command::SUCCESS;
	}
}
