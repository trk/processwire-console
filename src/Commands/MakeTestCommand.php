<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Traits\InteractWithProcessWire;

final class MakeTestCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('make:test')
			->setDescription('Create a new Pest test file.')
			->addArgument('name', InputArgument::REQUIRED, 'The name of the test file (e.g. UserTest)')
			->addOption('unit', 'u', InputOption::VALUE_NONE, 'Create a unit test')
			->addOption('module', 'm', InputOption::VALUE_REQUIRED, 'Module name to place the test in (e.g. MyCustomModule)')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$name = (string) $input->getArgument('name');
		$asJson = (bool) $input->getOption('json');

		if (!$this->isValidIdentifier($name)) {
			$msg = "Invalid test name '{$name}'. Must match /^[A-Za-z][A-Za-z0-9_]*$/.";
			if ($asJson) {
				return $this->outputJsonError($output, 'INVALID_NAME', $msg);
			}
			\Laravel\Prompts\error($msg);
			return Command::FAILURE;
		}

		if (!str_ends_with($name, 'Test')) {
			$name .= 'Test';
		}

		$module = $input->getOption('module') ? (string) $input->getOption('module') : null;
		if ($module !== null && !$this->isValidIdentifier($module)) {
			$msg = "Invalid module name '{$module}'. Must match /^[A-Za-z][A-Za-z0-9_]*$/.";
			if ($asJson) {
				return $this->outputJsonError($output, 'INVALID_MODULE', $msg);
			}
			\Laravel\Prompts\error($msg);
			return Command::FAILURE;
		}

		$isUnit = (bool) $input->getOption('unit');
		$config = \ProcessWire\wire('config');
		$subFolder = $isUnit ? 'Unit/' : 'Feature/';

		if ($module) {
			$targetDir = rtrim($config->paths->siteModules, '/') . '/' . $module . '/tests/' . $subFolder;
		} else {
			$targetDir = rtrim($config->paths->site, '/') . '/tests/' . $subFolder;
		}

		if (!is_dir($targetDir)) {
			mkdir($targetDir, 0755, true);
		}

		$filePath = $targetDir . $name . '.php';

		if (file_exists($filePath)) {
			$msg = "Test file already exists: {$name}.php";
			if ($asJson) {
				return $this->outputJsonError($output, 'ALREADY_EXISTS', $msg);
			}
			\Laravel\Prompts\error($msg);
			return Command::FAILURE;
		}

		$content = $this->loadStub('pest.stub');
		if ($content === null) {
			$msg = 'Stub file not found: pest.stub';
			if ($asJson) {
				return $this->outputJsonError($output, 'STUB_NOT_FOUND', $msg);
			}
			\Laravel\Prompts\error($msg);
			return Command::FAILURE;
		}

		file_put_contents($filePath, $content);

		if ($asJson) {
			return $this->outputJsonSuccess($output, [
				'name' => $name,
				'unit' => $isUnit,
				'path' => $filePath,
			]);
		}

		\Laravel\Prompts\info("Created test file: {$name}");
		\Laravel\Prompts\note("Path: {$filePath}");

		return Command::SUCCESS;
	}
}
