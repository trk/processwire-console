<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Traits\InteractWithProcessWire;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\text;

final class MakeModuleCommand extends Command
{
	use InteractWithProcessWire;

	private const STUB_TYPES = [
		'module' => 'module.stub',
		'fieldtype' => 'fieldtype.stub',
		'inputfield' => 'inputfield.stub',
		'process' => 'process.stub',
	];

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

		if ($name === '' && $this->canPrompt($input, $asJson)) {
			$name = text(
				label: 'What is the name of the module? (e.g. HelloWorld)',
				required: true
			);
		}

		if (!$this->isValidIdentifier($name)) {
			return $this->failCommand(
				$output,
				$asJson,
				'INVALID_NAME',
				"Invalid module class name '{$name}'. Must match /^[A-Za-z][A-Za-z0-9_]*$/."
			);
		}

		$type = strtolower(trim((string) $input->getOption('type')));
		if (!isset(self::STUB_TYPES[$type])) {
			return $this->failCommand(
				$output,
				$asJson,
				'INVALID_TYPE',
				"Invalid module type '{$type}'. Valid types: " . implode(', ', array_keys(self::STUB_TYPES))
			);
		}

		$title = $input->getOption('title') ? (string) $input->getOption('title') : $name;
		$summary = $input->getOption('summary') ? (string) $input->getOption('summary') : "A new {$type} module created via wire-cli.";
		$author = (string) $input->getOption('author');
		$version = (string) $input->getOption('mod-version');
		$autoloadFlag = (bool) $input->getOption('autoload');

		$modulesPath = (string) \ProcessWire\wire('config')->paths->siteModules;
		$modulePath = $modulesPath . $name;

		if (is_dir($modulePath)) {
			return $this->failCommand($output, $asJson, 'ALREADY_EXISTS', "Module directory '{$name}' already exists.");
		}

		$stubFile = self::STUB_TYPES[$type];
		$baseName = (string) preg_replace('/^(Fieldtype|Inputfield|Process)/', '', $name);
		if ($baseName === '') {
			$baseName = $name;
		}

		$sanitizeInline = static fn(string $val): string => trim(str_replace(['*/', "\r", "\n"], ['* /', ' ', ' '], $val));
		$escapeSingle = static fn(string $val): string => addcslashes($sanitizeInline($val), "\\'");

		$content = $this->loadStub($stubFile, [
			'{{className}}' => $name,
			'{{baseName}}' => $baseName,
			'{{title}}' => $escapeSingle($title),
			'{{summary}}' => $escapeSingle($summary),
			'{{author}}' => $escapeSingle($author),
			'{{name}}' => $escapeSingle((string) preg_replace('/[^a-z0-9_-]/', '-', strtolower($name))),
			"'version' => '0.0.1'" => "'version' => '" . $escapeSingle($version) . "'",
		]);
		if ($content === null) {
			return $this->failCommand($output, $asJson, 'STUB_NOT_FOUND', "Stub file not found: {$stubFile}");
		}

		if ($type === 'module' && !$autoloadFlag) {
			$content = str_replace("'autoload' => true", "'autoload' => false", $content);
		}

		if (!mkdir($modulePath, 0755, true) && !is_dir($modulePath)) {
			return $this->failCommand($output, $asJson, 'WRITE_FAILED', "Failed to create module directory: {$modulePath}");
		}
		$moduleFile = "{$modulePath}/{$name}.module.php";
		if (file_put_contents($moduleFile, $content) === false) {
			return $this->failCommand($output, $asJson, 'WRITE_FAILED', "Failed to write module file: {$moduleFile}");
		}

		if ($asJson) {
			return $this->outputJsonSuccess($output, [
				'name' => $name,
				'type' => $type,
				'path' => $moduleFile,
			]);
		}

		info("{$type} '{$name}' scaffolded from stub at: {$moduleFile}");
		note("You can now install it via the ProcessWire admin or 'php vendor/bin/wire module:install --name={$name}'.");

		return Command::SUCCESS;
	}
}
