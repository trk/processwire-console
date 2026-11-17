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
			->addOption('field', null, InputOption::VALUE_REQUIRED, 'Field name (for create-field and attach-field types)')
			->addOption('fieldtype', null, InputOption::VALUE_REQUIRED, 'Fieldtype module name (for create-field)', 'FieldtypeText')
			->addOption('template', null, InputOption::VALUE_REQUIRED, 'Template name (for create-template, attach-field, and create-page types)')
			->addOption('role', null, InputOption::VALUE_REQUIRED, 'Role name (for create-role type)')
			->addOption('label', null, InputOption::VALUE_REQUIRED, 'Label or title for the created item')
			->addOption('parent', null, InputOption::VALUE_REQUIRED, 'Parent page path (for create-page)', '/')
			->addOption('module', 'm', InputOption::VALUE_REQUIRED, 'Target module directory (or module to install when --type=install-module)')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$name = (string) $input->getArgument('name');
		$type = (string) $input->getOption('type');
		$asJson = (bool) $input->getOption('json');

		if (!$this->isValidIdentifier($name)) {
			return $this->failCommand(
				$output,
				$asJson,
				'INVALID_NAME',
				"Invalid migration name '{$name}'. Must match /^[A-Za-z][A-Za-z0-9_]*$/."
			);
		}

		$rawModule = $input->getOption('module');
		$moduleOpt = ($rawModule !== null && $rawModule !== '') ? (string) $rawModule : null;
		if ($moduleOpt !== null && !$this->isValidIdentifier($moduleOpt)) {
			return $this->failCommand(
				$output,
				$asJson,
				'INVALID_MODULE',
				"Invalid module name '{$moduleOpt}'. Must match /^[A-Za-z][A-Za-z0-9_]*$/."
			);
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
			return $this->failCommand(
				$output,
				$asJson,
				'INVALID_TYPE',
				"Unknown migration type: {$type}. Valid types: " . implode(', ', array_keys($stubMap))
			);
		}

		$validationError = $this->validateMigrationTypeOptions($input, $type);
		if ($validationError !== null) {
			return $this->failCommand($output, $asJson, $validationError['code'], $validationError['message']);
		}

		if ($moduleOpt !== null && $type !== 'install-module') {
			$config = \ProcessWire\wire('config');
			$targetDir = rtrim((string) $config->paths->siteModules, '/') . '/' . $moduleOpt . '/migrations/';
			if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
				return $this->failCommand($output, $asJson, 'WRITE_FAILED', "Failed to create directory: {$targetDir}");
			}
		} else {
			$migrator = new Migrator();
			$migrator->ensureMigrationsDirectory();
			$targetDir = $migrator->getMigrationsPath();
		}

		$safeName = (string) preg_replace('/[^a-z0-9_]/', '_', strtolower($name));
		$baseEpoch = time();
		$fileName = date('YmdHis', $baseEpoch) . "_{$safeName}.php";
		$filePath = $targetDir . $fileName;

		while (file_exists($filePath)) {
			$baseEpoch++;
			$fileName = date('YmdHis', $baseEpoch) . "_{$safeName}.php";
			$filePath = $targetDir . $fileName;
		}

		$replacements = [];
		foreach ($this->buildReplacements($input, $name, $type) as $placeholder => $value) {
			$cleanVal = str_replace(["\r", "\n"], ' ', $value);
			$replacements["{{{$placeholder}}}"] = addcslashes($cleanVal, "\\'");
		}

		$content = $this->loadStub($stubFile, $replacements);
		if ($content === null) {
			return $this->failCommand($output, $asJson, 'STUB_NOT_FOUND', "Stub file not found: {$stubFile}");
		}

		if (file_put_contents($filePath, $content) === false) {
			return $this->failCommand($output, $asJson, 'WRITE_FAILED', "Failed to write migration file: {$filePath}");
		}

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
	 * Validate schema/path options before interpolating them into migration stubs.
	 *
	 * @return array{code: string, message: string}|null
	 */
	public function validateMigrationTypeOptions(InputInterface $input, string $type): ?array
	{
		$templateOpt = $input->getOption('template');
		if ($templateOpt !== null && $templateOpt !== '') {
			$tpl = (string) $templateOpt;
			if (preg_match('/^[A-Za-z0-9_-]+$/', $tpl) !== 1) {
				return [
					'code' => 'INVALID_TEMPLATE',
					'message' => "Invalid template name '{$tpl}'. Must match /^[A-Za-z0-9_-]+$/.",
				];
			}
		}

		$fieldOpt = $input->getOption('field');
		if ($fieldOpt !== null && $fieldOpt !== '' && $type !== 'create-role') {
			$fld = (string) $fieldOpt;
			if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $fld) !== 1) {
				return [
					'code' => 'INVALID_FIELD',
					'message' => "Invalid field name '{$fld}'. Must match /^[A-Za-z_][A-Za-z0-9_]*$/.",
				];
			}
		}

		if ($type === 'create-field') {
			$fieldtype = (string) $input->getOption('fieldtype');
			if (!$this->isValidIdentifier($fieldtype)) {
				return [
					'code' => 'INVALID_FIELDTYPE',
					'message' => "Invalid fieldtype '{$fieldtype}'. Must match /^[A-Za-z][A-Za-z0-9_]*$/.",
				];
			}
		}

		if ($type === 'create-role') {
			$roleOpt = $input->getOption('role') ?: $input->getOption('field');
			if ($roleOpt !== null && $roleOpt !== '') {
				$roleStr = (string) $roleOpt;
				if (preg_match('/^[A-Za-z0-9_-]+$/', $roleStr) !== 1) {
					return [
						'code' => 'INVALID_ROLE',
						'message' => "Invalid role name '{$roleStr}'. Must match /^[A-Za-z0-9_-]+$/.",
					];
				}
			}
		}

		if ($type === 'create-page') {
			$parent = (string) $input->getOption('parent');
			if (!str_starts_with($parent, '/')) {
				return [
					'code' => 'INVALID_PARENT',
					'message' => "Invalid parent path '{$parent}'. Parent path must start with '/'.",
				];
			}
		}

		return null;
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
				$replacements['name'] = (string) ($input->getOption('field') ?: strtolower($name));
				$replacements['fieldtype'] = (string) $input->getOption('fieldtype');
				$replacements['label'] = (string) ($input->getOption('label') ?: ucfirst(str_replace('_', ' ', $replacements['name'])));
				break;

			case 'create-template':
				$replacements['name'] = (string) ($input->getOption('template') ?: str_replace('_', '-', strtolower($name)));
				$replacements['label'] = (string) ($input->getOption('label') ?: ucfirst(str_replace(['_', '-'], ' ', $replacements['name'])));
				break;

			case 'attach-field':
				$replacements['template'] = (string) ($input->getOption('template') ?: 'TEMPLATE_NAME');
				$replacements['field'] = (string) ($input->getOption('field') ?: 'FIELD_NAME');
				break;

			case 'create-page':
				$replacements['parent'] = (string) $input->getOption('parent');
				$replacements['template'] = (string) ($input->getOption('template') ?: 'basic-page');
				$replacements['name'] = (string) preg_replace('/[^a-z0-9\-]/', '-', strtolower($name));
				$replacements['title'] = (string) ($input->getOption('label') ?: ucfirst(str_replace(['_', '-'], ' ', $name)));
				break;

			case 'create-role':
				$derivedRole = (string) preg_replace('/^create_(.+)_role$/', '$1', strtolower($name));
				$roleSource = (string) ($input->getOption('role') ?: $input->getOption('field') ?: $derivedRole);
				$replacements['name'] = (string) preg_replace('/[^a-z0-9\-]/', '-', strtolower($roleSource));
				$replacements['title'] = (string) ($input->getOption('label') ?: ucfirst(str_replace(['_', '-'], ' ', $replacements['name'])));
				break;

			case 'install-module':
				$replacements['name'] = (string) ($input->getOption('module') ?: $name);
				break;
		}

		return $replacements;
	}
}
