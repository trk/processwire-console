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
		$name = $input->getOption('name') ? trim((string) $input->getOption('name')) : '';
		$dryRun = (bool) $input->getOption('dry-run');
		$asJson = (bool) $input->getOption('json');

		if ($name === '' && $this->canPrompt($input, $asJson)) {
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
		if (!$installed && !$modules->getModuleFile($name)) {
			return $this->failCommand($output, $asJson, 'NOT_FOUND', "Module not found: {$name}");
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
			$modules->install($name);
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
