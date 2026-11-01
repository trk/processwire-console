<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Traits\InteractWithProcessWire;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\text;

final class MakeModuleCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('make:module')
			->setDescription('Scaffold a new ProcessWire module.')
			->addArgument('name', InputArgument::OPTIONAL, 'The name of the module (e.g. HelloWorld)')
			->addOption('type', 't', InputOption::VALUE_REQUIRED, 'Type of module (module, fieldtype, inputfield, process)', 'module')
			->addOption('title', null, InputOption::VALUE_REQUIRED, 'Module title (defaults to name)')
			->addOption('summary', null, InputOption::VALUE_REQUIRED, 'Short summary')
			->addOption('author', null, InputOption::VALUE_REQUIRED, 'Author name', 'Wire-CLI Builder')
			->addOption('mod-version', null, InputOption::VALUE_REQUIRED, 'Module version string', '0.0.1')
			->addOption('autoload', null, InputOption::VALUE_NONE, 'Set autoload=true in stub (module type only)')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$name = $input->getArgument('name') ? (string) $input->getArgument('name') : '';
		$asJson = (bool) $input->getOption('json');

		if (!$name && $input->isInteractive() && !$asJson) {
			$name = text(
				label: 'What is the name of the module? (e.g. HelloWorld)',
				required: true
			);
		}

		if (!$this->isValidIdentifier($name)) {
			$msg = "Invalid module class name '{$name}'. Must match /^[A-Za-z][A-Za-z0-9_]*$/.";
			return $asJson ? $this->outputJsonError($output, 'INVALID_NAME', $msg) : (error($msg) ?? Command::FAILURE);
		}

		$type = strtolower((string) $input->getOption('type'));
		$title = $input->getOption('title') ? (string) $input->getOption('title') : $name;
		$summary = $input->getOption('summary') ? (string) $input->getOption('summary') : "A new {$type} module created via wire-cli.";
		$author = (string) $input->getOption('author');
		$version = (string) $input->getOption('mod-version');
		$autoloadFlag = (bool) $input->getOption('autoload');

		$modulesPath = \ProcessWire\wire('config')->paths->siteModules;
		$modulePath = $modulesPath . $name;

		if (is_dir($modulePath)) {
			$msg = "Module directory '{$name}' already exists.";
			return $asJson ? $this->outputJsonError($output, 'ALREADY_EXISTS', $msg) : (error($msg) ?? Command::FAILURE);
		}

		$stubFile = match ($type) {
			'fieldtype' => 'fieldtype.stub',
			'inputfield' => 'inputfield.stub',
			'process' => 'process.stub',
			default => 'module.stub',
		};

		$baseName = (string) preg_replace('/^Fieldtype/', '', $name);
		if ($baseName === '') {
			$baseName = $name;
		}

		$content = $this->loadStub($stubFile, [
			'{{className}}' => $name,
			'{{baseName}}' => $baseName,
			'{{title}}' => addslashes($title),
			'{{summary}}' => addslashes($summary),
			'{{author}}' => addslashes($author),
			'{{name}}' => addslashes(strtolower($name)),
			"'version' => '0.0.1'" => "'version' => '" . addslashes($version) . "'",
		]);
		if ($content === null) {
			$msg = "Stub file not found: {$stubFile}";
			return $asJson ? $this->outputJsonError($output, 'STUB_NOT_FOUND', $msg) : (error($msg) ?? Command::FAILURE);
		}

		if ($type === 'module' && !$autoloadFlag) {
			$content = str_replace("'autoload' => true", "'autoload' => false", $content);
		}

		mkdir($modulePath, 0755, true);
		$moduleFile = "{$modulePath}/{$name}.module.php";
		file_put_contents($moduleFile, $content);

		if ($asJson) {
			return $this->outputJsonSuccess($output, [
				'name' => $name,
				'type' => $type,
				'path' => $moduleFile,
			]);
		}

		info("{$type} '{$name}' scaffolded from stub at: {$moduleFile}");
		note("You can now install it via the ProcessWire admin or 'wire module:list'.");

		return Command::SUCCESS;
	}
}
