<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Traits\InteractWithProcessWire;
use function Laravel\Prompts\info;

final class ModuleUpgradeCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('module:upgrade')
			->setDescription('Attempt to upgrade a ProcessWire module (refresh and trigger version upgrade check).')
			->addOption('name', null, InputOption::VALUE_OPTIONAL, 'Module class name')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$name = $input->getOption('name') ? trim((string) $input->getOption('name')) : '';
		$asJson = (bool) $input->getOption('json');

		if ($name === '' && $this->canPrompt($input, $asJson)) {
			$name = $this->searchInstalledModule('Select a module to upgrade');
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
		$modules->refresh();
		$installed = $modules->isInstalled($name);

		if (!$installed && !$modules->getModuleFile($name)) {
			return $this->failCommand($output, $asJson, 'NOT_FOUND', "Module not found: {$name}");
		}

		if ($installed) {
			try {
				if (!$asJson) {
					info("Upgrading module '{$name}'...");
				}
				// Loading an installed module after refresh triggers ProcessWire's native module version check and ___upgrade()
				$modules->get($name);
			} catch (\Throwable $e) {
				return $this->failCommand($output, $asJson, 'UPGRADE_FAILED', "Upgrade step failed for '{$name}': " . $e->getMessage());
			}
		}

		$data = ['name' => $name, 'refreshed' => true, 'upgraded' => $installed];
		if ($asJson) {
			return $this->outputJsonSuccess($output, $data);
		}

		info('Modules refreshed' . ($installed ? " and '{$name}' upgrade routine checked" : '') . '.');
		return Command::SUCCESS;
	}
}
