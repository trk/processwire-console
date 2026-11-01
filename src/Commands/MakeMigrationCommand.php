<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Migration\Migrator;
use Totoglu\Console\Traits\InteractWithProcessWire;

final class MakeMigrationCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('make:migration')
			->setDescription('Create a new migration file.')
			->addArgument('name', InputArgument::REQUIRED, 'Migration name (e.g. create_blog_template)')
			->addOption('type', 't', InputOption::VALUE_REQUIRED, 'Stub type: blank, create-field, create-template, attach-field, create-page, create-role, install-module', 'blank')
			->addOption('field', null, InputOption::VALUE_REQUIRED, 'Field name (for create-field, attach-field, and create-role types)')
			->addOption('fieldtype', null, InputOption::VALUE_REQUIRED, 'Fieldtype module name (for create-field)', 'FieldtypeText')
			->addOption('template', null, InputOption::VALUE_REQUIRED, 'Template name (for create-template and attach-field types)')
			->addOption('label', null, InputOption::VALUE_REQUIRED, 'Label for the created item')
			->addOption('parent', null, InputOption::VALUE_REQUIRED, 'Parent page path (for create-page)', '/')
			->addOption('module', null, InputOption::VALUE_REQUIRED, 'Module class name (for install-module)')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$name = (string) $input->getArgument('name');
		$type = (string) $input->getOption('type');
		$asJson = (bool) $input->getOption('json');

		if (!$this->isValidIdentifier($name)) {
			$msg = "Invalid migration name '{$name}'. Must match /^[A-Za-z][A-Za-z0-9_]*$/.";
			if ($asJson) {
				return $this->outputJsonError($output, 'INVALID_NAME', $msg);
			}
			\Laravel\Prompts\error($msg);
			return Command::FAILURE;
		}

		$moduleOpt = $input->getOption('module') ? (string) $input->getOption('module') : null;
		if ($moduleOpt !== null && !$this->isValidIdentifier($moduleOpt)) {
			$msg = "Invalid module name '{$moduleOpt}'. Must match /^[A-Za-z][A-Za-z0-9_]*$/.";
			if ($asJson) {
				return $this->outputJsonError($output, 'INVALID_MODULE', $msg);
			}
			\Laravel\Prompts\error($msg);
			return Command::FAILURE;
		}

		$migrator = new Migrator();
		$migrator->ensureMigrationsDirectory();

		$timestamp = date('YmdHis');
		$safeName = (string) preg_replace('/[^a-z0-9_]/', '_', strtolower($name));
		$fileName = "{$timestamp}_{$safeName}.php";
		$filePath = $migrator->getMigrationsPath() . $fileName;

		if (file_exists($filePath)) {
			$msg = "Migration file already exists: {$fileName}";
			if ($asJson) {
				return $this->outputJsonError($output, 'ALREADY_EXISTS', $msg);
			}
			\Laravel\Prompts\error($msg);
			return Command::FAILURE;
		}

		$stubMap = [
			'blank' => 'migration.stub',
			'create-field' => 'migration.create-field.stub',
			'create-template' => 'migration.create-template.stub',
			'attach-field' => 'migration.attach-field.stub',
			'create-page' => 'migration.create-page.stub',
			'create-role' => 'migration.create-role.stub',
			'install-module' => 'migration.install-module.stub',
		];

		$stubFile = $stubMap[$type] ?? null;
		if ($stubFile === null) {
			$msg = "Unknown migration type: {$type}. Valid types: " . implode(', ', array_keys($stubMap));
			if ($asJson) {
				return $this->outputJsonError($output, 'INVALID_TYPE', $msg);
			}
			\Laravel\Prompts\error($msg);
			return Command::FAILURE;
		}

		$replacements = [];
		foreach ($this->buildReplacements($input, $name, $type) as $placeholder => $value) {
			$replacements["{{{$placeholder}}}"] = addslashes($value);
		}

		$content = $this->loadStub($stubFile, $replacements);
		if ($content === null) {
			$msg = "Stub file not found: {$stubFile}";
			if ($asJson) {
				return $this->outputJsonError($output, 'STUB_NOT_FOUND', $msg);
			}
			\Laravel\Prompts\error($msg);
			return Command::FAILURE;
		}

		file_put_contents($filePath, $content);

		if ($asJson) {
			return $this->outputJsonSuccess($output, [
				'file' => $fileName,
				'path' => $filePath,
				'type' => $type,
			]);
		}

		\Laravel\Prompts\info("Created migration: {$fileName}");
		\Laravel\Prompts\note("Path: {$filePath}");

		return Command::SUCCESS;
	}

	/**
	 * Build placeholder replacements based on migration type.
	 *
	 * @return array<string, string>
	 */
	private function buildReplacements(InputInterface $input, string $name, string $type): array
	{
		$replacements = [];

		switch ($type) {
			case 'create-field':
				$replacements['name'] = (string) ($input->getOption('field') ?: $name);
				$replacements['fieldtype'] = (string) $input->getOption('fieldtype');
				$replacements['label'] = (string) ($input->getOption('label') ?: ucfirst(str_replace('_', ' ', $replacements['name'])));
				break;

			case 'create-template':
				$replacements['name'] = (string) ($input->getOption('template') ?: $name);
				$replacements['label'] = (string) ($input->getOption('label') ?: ucfirst(str_replace(['_', '-'], ' ', $replacements['name'])));
				break;

			case 'attach-field':
				$replacements['template'] = (string) ($input->getOption('template') ?: '');
				$replacements['field'] = (string) ($input->getOption('field') ?: '');
				if (!$replacements['template'] || !$replacements['field']) {
					$replacements['template'] = $replacements['template'] ?: 'TEMPLATE_NAME';
					$replacements['field'] = $replacements['field'] ?: 'FIELD_NAME';
				}
				break;

			case 'create-page':
				$replacements['parent'] = (string) $input->getOption('parent');
				$replacements['template'] = (string) ($input->getOption('template') ?: 'basic-page');
				$replacements['name'] = (string) preg_replace('/[^a-z0-9\-]/', '-', strtolower($name));
				$replacements['title'] = (string) ($input->getOption('label') ?: ucfirst(str_replace(['_', '-'], ' ', $name)));
				break;

			case 'create-role':
				$replacements['name'] = (string) ($input->getOption('field') ?: preg_replace('/[^a-z0-9\-]/', '-', strtolower($name)));
				$replacements['title'] = (string) ($input->getOption('label') ?: ucfirst(str_replace(['_', '-'], ' ', $name)));
				break;

			case 'install-module':
				$replacements['name'] = (string) ($input->getOption('module') ?: $name);
				break;
		}

		return $replacements;
	}
}
