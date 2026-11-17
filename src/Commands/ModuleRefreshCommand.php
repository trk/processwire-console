<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Traits\InteractWithProcessWire;
use function Laravel\Prompts\info;

final class ModuleRefreshCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('module:refresh')
			->setDescription('Refresh modules (single or all).')
			->addOption('name', null, InputOption::VALUE_REQUIRED, 'Optional module class name (refresh all if omitted)')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$name = $input->getOption('name') ? trim((string) $input->getOption('name')) : '';
		$asJson = (bool) $input->getOption('json');

		if ($name !== '' && !$this->isValidIdentifier($name)) {
			return $this->failCommand($output, $asJson, 'INVALID_NAME', "Invalid module class name '{$name}'.");
		}

		$modules = \ProcessWire\wire('modules');
		$modules->refresh();

		if ($name !== '') {
			if (!$modules->isInstalled($name) && !$modules->getModuleFile($name)) {
				return $this->failCommand($output, $asJson, 'NOT_FOUND', "Module not found: {$name}");
			}
			$data = ['name' => $name, 'refreshed' => true];
		} else {
			$data = ['all' => true, 'refreshed' => true];
		}

		if ($asJson) {
			return $this->outputJsonSuccess($output, $data);
		}

		if ($name !== '') {
			info("Refreshed modules (including '{$name}').");
		} else {
			info('Refreshed all modules.');
		}

		return Command::SUCCESS;
	}
}

