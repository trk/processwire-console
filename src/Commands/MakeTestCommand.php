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
			->setDescription('Create a new Pest PHP test or Playwright E2E spec file.')
			->addArgument('name', InputArgument::REQUIRED, 'The name of the test file (e.g. UserTest or workspace-flow)')
			->addOption('unit', 'u', InputOption::VALUE_NONE, 'Create a Pest unit test')
			->addOption('e2e', 'e', InputOption::VALUE_NONE, 'Create a Playwright E2E spec (.spec.ts)')
			->addOption('module', 'm', InputOption::VALUE_REQUIRED, 'Module name to place the test in (e.g. MyCustomModule)')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$name = (string) $input->getArgument('name');
		$asJson = (bool) $input->getOption('json');
		$isUnit = (bool) $input->getOption('unit');
		$isE2e = (bool) $input->getOption('e2e');

		if ($isUnit && $isE2e) {
			return $this->failCommand($output, $asJson, 'INVALID_OPTIONS', 'Cannot combine --unit and --e2e options.');
		}

		$rawModule = $input->getOption('module');
		$module = ($rawModule !== null && $rawModule !== '') ? (string) $rawModule : null;
		if ($module !== null && !$this->isValidIdentifier($module)) {
			return $this->failCommand(
				$output,
				$asJson,
				'INVALID_MODULE',
				"Invalid module name '{$module}'. Must match /^[A-Za-z][A-Za-z0-9_]*$/."
			);
		}

		if ($isE2e) {
			return $this->executeE2eScaffold($name, $module, $asJson, $output);
		}

		if (!$this->isValidIdentifier($name)) {
			return $this->failCommand(
				$output,
				$asJson,
				'INVALID_NAME',
				"Invalid test name '{$name}'. Must match /^[A-Za-z][A-Za-z0-9_]*$/."
			);
		}

		if (!str_ends_with($name, 'Test')) {
			$name .= 'Test';
		}

		$config = \ProcessWire\wire('config');
		$subFolder = $isUnit ? 'Unit/' : 'Feature/';

		if ($module !== null) {
			$targetDir = rtrim((string) $config->paths->siteModules, '/') . '/' . $module . '/tests/' . $subFolder;
		} else {
			$targetDir = rtrim((string) $config->paths->site, '/') . '/tests/' . $subFolder;
		}

		if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
			return $this->failCommand($output, $asJson, 'WRITE_FAILED', "Failed to create directory: {$targetDir}");
		}

		$filePath = $targetDir . $name . '.php';

		if (file_exists($filePath)) {
			return $this->failCommand($output, $asJson, 'ALREADY_EXISTS', "Test file already exists: {$name}.php");
		}

		$content = $this->loadStub('pest.stub');
		if ($content === null) {
			return $this->failCommand($output, $asJson, 'STUB_NOT_FOUND', 'Stub file not found: pest.stub');
		}

		if (file_put_contents($filePath, $content) === false) {
			return $this->failCommand($output, $asJson, 'WRITE_FAILED', "Failed to write test file: {$filePath}");
		}

		if ($asJson) {
			return $this->outputJsonSuccess($output, [
				'name' => $name,
				'unit' => $isUnit,
				'e2e' => false,
				'path' => $filePath,
			]);
		}

		\Laravel\Prompts\info("Created test file: {$name}");
		\Laravel\Prompts\note("Path: {$filePath}");

		return Command::SUCCESS;
	}

	private function executeE2eScaffold(string $rawName, ?string $module, bool $asJson, OutputInterface $output): int
	{
		$slug = self::normalizeE2eSpecSlug($rawName);
		if ($slug === null) {
			return $this->failCommand(
				$output,
				$asJson,
				'INVALID_NAME',
				"Invalid E2E spec name '{$rawName}'. Must start with a letter and contain only letters, digits, underscores, or hyphens."
			);
		}

		$config = \ProcessWire\wire('config');
		$sitePath = rtrim((string) $config->paths->site, '/');
		$projectRoot = dirname($sitePath);

		if ($module !== null) {
			$targetDir = rtrim((string) $config->paths->siteModules, '/') . '/' . $module . '/tests/e2e/';
		} else {
			$targetDir = $sitePath . '/tests/e2e/';
		}

		if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
			return $this->failCommand($output, $asJson, 'WRITE_FAILED', "Failed to create directory: {$targetDir}");
		}

		$fileName = $slug . '.spec.ts';
		$filePath = $targetDir . $fileName;

		if (file_exists($filePath)) {
			return $this->failCommand($output, $asJson, 'ALREADY_EXISTS', "E2E spec file already exists: {$fileName}");
		}

		$humanTitle = self::humanizeSlug($slug);
		$suiteTitle = str_replace(["\r", "\n"], ' ', $module !== null ? "{$module}: {$humanTitle}" : $humanTitle);
		$helpersImportPath = self::computeRelativeImportPath($targetDir, $projectRoot . '/tests/e2e/helpers');

		$content = $this->loadStub('playwright.spec.stub', [
			'{{title}}' => addcslashes($suiteTitle, "\\'"),
			'{{helpersImportPath}}' => addcslashes($helpersImportPath, "\\'\r\n"),
		]);
		if ($content === null) {
			return $this->failCommand($output, $asJson, 'STUB_NOT_FOUND', 'Stub file not found: playwright.spec.stub');
		}

		if (file_put_contents($filePath, $content) === false) {
			return $this->failCommand($output, $asJson, 'WRITE_FAILED', "Failed to write E2E spec file: {$filePath}");
		}

		if ($asJson) {
			return $this->outputJsonSuccess($output, [
				'name' => $fileName,
				'unit' => false,
				'e2e' => true,
				'module' => $module,
				'path' => $filePath,
			]);
		}

		\Laravel\Prompts\info("Created Playwright E2E spec: {$fileName}");
		\Laravel\Prompts\note("Path: {$filePath}");

		return Command::SUCCESS;
	}

	/**
	 * Normalize a user-supplied test identifier into a kebab-case Playwright spec slug.
	 */
	public static function normalizeE2eSpecSlug(string $rawName): ?string
	{
		$trimmed = trim($rawName);
		if (str_ends_with($trimmed, '.spec.ts')) {
			$trimmed = substr($trimmed, 0, -8);
		} elseif (str_ends_with($trimmed, '.spec')) {
			$trimmed = substr($trimmed, 0, -5);
		}

		if (preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $trimmed) !== 1) {
			return null;
		}

		if (strlen($trimmed) > 4 && (str_ends_with($trimmed, 'Test') || str_ends_with($trimmed, 'Spec'))) {
			$trimmed = substr($trimmed, 0, -4);
		}

		$kebab = (string) preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1-$2', $trimmed);
		$kebab = (string) preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', $kebab);
		$kebab = str_replace('_', '-', $kebab);
		$kebab = (string) preg_replace('/-+/', '-', $kebab);
		$kebab = trim(strtolower($kebab), '-');

		return $kebab !== '' ? $kebab : null;
	}

	/**
	 * Convert a kebab-case slug into Title Case.
	 */
	public static function humanizeSlug(string $slug): string
	{
		return ucwords(str_replace('-', ' ', $slug));
	}

	/**
	 * Compute a POSIX relative import path from a directory to a target path without extension.
	 */
	public static function computeRelativeImportPath(string $fromDir, string $toFileWithoutExt): string
	{
		$fromParts = array_values(array_filter(explode('/', str_replace('\\', '/', rtrim($fromDir, '/'))), static fn(string $p): bool => $p !== ''));
		$toParts = array_values(array_filter(explode('/', str_replace('\\', '/', $toFileWithoutExt)), static fn(string $p): bool => $p !== ''));

		$commonLength = 0;
		$maxCommon = min(count($fromParts), count($toParts));
		while ($commonLength < $maxCommon && $fromParts[$commonLength] === $toParts[$commonLength]) {
			$commonLength++;
		}

		$upCount = count($fromParts) - $commonLength;
		$prefix = $upCount > 0 ? str_repeat('../', $upCount) : './';
		$remaining = array_slice($toParts, $commonLength);

		return $prefix . implode('/', $remaining);
	}
}
