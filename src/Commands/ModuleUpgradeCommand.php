<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Traits\InteractWithProcessWire;
use function Laravel\Prompts\error;
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
		$name = $input->getOption('name') ? (string) $input->getOption('name') : '';
		$asJson = (bool) $input->getOption('json');

		if (!$name && !$asJson) {
			$name = $this->searchInstalledModule('Select a module to upgrade');
			if (empty($name)) {
				return Command::SUCCESS;
			}
		}

		if (!$name) {
			$msg = 'Provide --name.';
			return $asJson ? $this->outputJsonError($output, 'MISSING_NAME', $msg) : (error($msg) ?? Command::FAILURE);
		}

		$modules = \ProcessWire\wire('modules');
		$modules->refresh();
		$installed = $modules->isInstalled($name);

		if ($installed) {
			try {
				if (!$asJson) {
					info("Upgrading module '{$name}'...");
				}
				// Loading an installed module after refresh triggers ProcessWire's native module version check and ___upgrade()
				$modules->get($name);
			} catch (\Throwable $e) {
				$msg = "Upgrade step failed for '{$name}': " . $e->getMessage();
				return $asJson ? $this->outputJsonError($output, 'UPGRADE_FAILED', $msg) : (error($msg) ?? Command::FAILURE);
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
