<?php

declare(strict_types=1);

namespace Totoglu\Console\Traits;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\search;
use function ProcessWire\wire;

trait InteractWithProcessWire
{
	/**
	 * Search for a ProcessWire Field interactively.
	 *
	 * @param string $label
	 * @return string Field name
	 */
	protected function searchField(string $label = 'Search for a field'): string
	{
		return $this->searchNamedWireItems($label, 'fields', 'Type field name...');
	}

	/**
	 * Search for a ProcessWire Template interactively.
	 *
	 * @param string $label
	 * @return string Template name
	 */
	protected function searchTemplate(string $label = 'Search for a template'): string
	{
		return $this->searchNamedWireItems($label, 'templates', 'Type template name...');
	}

	/**
	 * Search for a ProcessWire Role interactively.
	 *
	 * @param string $label
	 * @return string Role name
	 */
	protected function searchRole(string $label = 'Search for a role'): string
	{
		return $this->searchNamedWireItems($label, 'roles', 'Type role name...');
	}

	/**
	 * Search for a ProcessWire Permission interactively.
	 *
	 * @param string $label
	 * @return string Permission name
	 */
	protected function searchPermission(string $label = 'Search for a permission'): string
	{
		return $this->searchNamedWireItems($label, 'permissions', 'Type permission name...');
	}

	/**
	 * Search named items from a ProcessWire collection API variable (`fields`, `templates`, `roles`, `permissions`).
	 */
	private function searchNamedWireItems(string $label, string $apiVar, string $placeholder): string
	{
		return search(
			label: $label,
			options: function (string $value) use ($apiVar): array {
				$options = [];
				$sanitizer = wire('sanitizer');
				$q = $sanitizer->selectorValue($value);
				$items = wire($apiVar)->find("name%={$q}, limit=15");
				foreach ($items as $item) {
					$options[] = (string) $item->name;
				}
				return $options;
			},
			placeholder: $placeholder
		);
	}

	/**
	 * Search for a ProcessWire User interactively.
	 *
	 * @param string $label
	 * @return string User name
	 */
	protected function searchUser(string $label = 'Search for a user'): string
	{
		return search(
			label: $label,
			options: function (string $value): array {
				$options = [];
				$sanitizer = wire('sanitizer');
				$q = $sanitizer->selectorValue($value);
				$users = wire('users')->find("name|email%=$q, limit=15");
				foreach ($users as $user) {
					$options[] = (string) $user->name;
				}
				return $options;
			},
			placeholder: 'Type user name or email...'
		);
	}

	/**
	 * Search for a ProcessWire Page interactively.
	 *
	 * @param string $label
	 * @param bool $includeTrash Whether to search trashed pages
	 * @return string|int Page path or ID
	 */
	protected function searchPage(string $label = 'Search for a page', bool $includeTrash = false): string|int
	{
		return search(
			label: $label,
			options: function (string $value) use ($includeTrash): array {
				$options = [];
				$sanitizer = wire('sanitizer');
				$q = $sanitizer->selectorValue($value);
				$selector = "title|path|name%=$q, limit=15";
				if ($includeTrash) {
					$selector .= ', include=trash, status>=' . \ProcessWire\Page::statusTrash;
				}
				$pages = wire('pages')->find($selector);
				foreach ($pages as $page) {
					$options[$page->id] = "{$page->title} ({$page->path})";
				}
				return $options;
			},
			placeholder: 'Type page title or path...'
		);
	}

	/**
	 * Search for an installed ProcessWire Module interactively.
	 *
	 * @param string $label
	 * @return string Module class name
	 */
	protected function searchInstalledModule(string $label = 'Search for an installed module'): string
	{
		return search(
			label: $label,
			options: function (string $value): array {
				$options = [];
				$modules = wire('modules');
				foreach ($modules->getAll() as $name => $module) {
					if (empty($value) || stripos((string) $name, $value) !== false) {
						$options[] = (string) $name;
					}
				}

				return array_slice($options, 0, 15);
			},
			placeholder: 'Type module name...'
		);
	}

	/**
	 * Search for an installable ProcessWire Module interactively.
	 *
	 * @param string $label
	 * @return string Module class name
	 */
	protected function searchInstallableModule(string $label = 'Search for a module to install'): string
	{
		return search(
			label: $label,
			options: function (string $value): array {
				$options = [];
				$modules = wire('modules');
				$installable = $modules->getInstallable();

				foreach ($installable as $name) {
					if (empty($value) || stripos((string) $name, $value) !== false) {
						$options[] = (string) $name;
					}
				}

				return array_slice($options, 0, 15);
			},
			placeholder: 'Type module name...'
		);
	}

	/**
	 * Search for a ProcessWire Log interactively.
	 *
	 * @param string $label
	 * @return string Log name
	 */
	protected function searchLog(string $label = 'Search for a log'): string
	{
		return search(
			label: $label,
			options: function (string $value): array {
				$options = [];
				$logs = wire('log')->getLogs();
				foreach ($logs as $name => $logInfo) {
					if (empty($value) || stripos((string) $name, $value) !== false) {
						$options[] = (string) $name;
					}
				}

				return array_slice($options, 0, 15);
			},
			placeholder: 'Type log name...'
		);
	}

	/**
	 * Select multiple ProcessWire Logs interactively.
	 *
	 * @param string $label
	 * @return array<int, string> Selected log names
	 */
	protected function multiselectLogs(string $label = 'Select logs to clear'): array
	{
		$logs = wire('log')->getLogs();
		$options = array_keys($logs);

		if (empty($options)) {
			return [];
		}

		return multiselect(
			label: $label,
			options: $options,
			hint: 'Use Space to select, Enter to confirm.'
		);
	}

	/**
	 * Validate a PHP / ProcessWire class or module identifier.
	 */
	protected function isValidIdentifier(string $name): bool
	{
		return preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $name) === 1;
	}

	/**
	 * Load a scaffolding stub file from resources/stubs/ and apply placeholder replacements.
	 *
	 * @param array<string, string> $replacements
	 */
	protected function loadStub(string $stubFile, array $replacements = []): ?string
	{
		$stubPath = dirname(__DIR__, 2) . '/resources/stubs/' . ltrim($stubFile, '/');
		if (!is_file($stubPath)) {
			return null;
		}

		$content = file_get_contents($stubPath);
		if ($content === false) {
			return null;
		}

		if ($replacements !== []) {
			$content = str_replace(array_keys($replacements), array_values($replacements), $content);
		}

		return $content;
	}

	/**
	 * Parse key=value sets from repeatable --set="key=value" options.
	 *
	 * @param array<int, string|null>|string|null $raw
	 * @return array<string, string>
	 */
	protected function parseKeyValueSets(array|string|null $raw): array
	{
		if ($raw === null || $raw === '' || $raw === []) {
			return [];
		}

		$entries = is_array($raw) ? $raw : [$raw];

		$changes = [];
		foreach ($entries as $pair) {
			if (!is_string($pair)) {
				continue;
			}
			$pair = trim($pair);
			if ($pair === '') {
				continue;
			}
			$parts = explode('=', $pair, 2);
			if (count($parts) !== 2) {
				continue;
			}
			$k = trim($parts[0]);
			$v = trim($parts[1]);
			if ($k !== '') {
				$changes[$k] = $v;
			}
		}

		return $changes;
	}

	/**
	 * Output a standard machine-readable JSON success payload and return Command::SUCCESS.
	 *
	 * @param array<string, mixed> $data
	 */
	protected function outputJsonSuccess(OutputInterface $output, array $data = []): int
	{
		$output->writeln((string) json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_SLASHES));
		return Command::SUCCESS;
	}

	/**
	 * Output a standard machine-readable JSON error payload and return Command::FAILURE.
	 */
	protected function outputJsonError(OutputInterface $output, string $code, string $message): int
	{
		$output->writeln((string) json_encode([
			'ok' => false,
			'error' => [
				'code' => $code,
				'message' => $message,
			],
		], JSON_UNESCAPED_SLASHES));
		return Command::FAILURE;
	}

	/**
	 * Scaffold a site or module feature class (Queue, Seeder, or Task) from a stub file.
	 */
	protected function scaffoldFeatureClass(
		\Symfony\Component\Console\Input\InputInterface $input,
		OutputInterface $output,
		string $label,
		string $suffix,
		string $directory,
		string $defaultNamespace,
		string $stubFile,
		string $successMessagePrefix
	): int {
		$name = (string) $input->getArgument('name');
		$asJson = (bool) $input->getOption('json');

		if (!$this->isValidIdentifier($name)) {
			$msg = "Invalid {$label} class name '{$name}'. Must match /^[A-Za-z][A-Za-z0-9_]*$/.";
			if ($asJson) {
				return $this->outputJsonError($output, 'INVALID_NAME', $msg);
			}
			\Laravel\Prompts\error($msg);
			return Command::FAILURE;
		}

		if (!str_ends_with($name, $suffix)) {
			$name .= $suffix;
		}

		$rawModule = $input->getOption('module');
		$module = ($rawModule !== null && $rawModule !== '') ? (string) $rawModule : null;
		if ($module !== null && !$this->isValidIdentifier($module)) {
			$msg = "Invalid module name '{$module}'. Must match /^[A-Za-z][A-Za-z0-9_]*$/.";
			if ($asJson) {
				return $this->outputJsonError($output, 'INVALID_MODULE', $msg);
			}
			\Laravel\Prompts\error($msg);
			return Command::FAILURE;
		}

		$config = wire('config');
		if ($module !== null) {
			$targetDir = $config->paths->siteModules . $module . '/' . $directory . '/';
			$namespace = 'ProcessWire';
		} else {
			$targetDir = $config->paths->site . $directory . '/';
			$namespace = $defaultNamespace;
		}

		if (!is_dir($targetDir)) {
			mkdir($targetDir, 0755, true);
		}

		$filePath = $targetDir . $name . '.php';
		if (file_exists($filePath)) {
			$ucLabel = ucfirst($label);
			$msg = "{$ucLabel} file already exists: {$name}.php";
			if ($asJson) {
				return $this->outputJsonError($output, 'ALREADY_EXISTS', $msg);
			}
			\Laravel\Prompts\error($msg);
			return Command::FAILURE;
		}

		$content = $this->loadStub($stubFile, [
			'{{namespace}}' => $namespace,
			'{{class}}' => $name,
		]);
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
				'class' => $name,
				'namespace' => $namespace,
				'path' => $filePath,
			]);
		}

		\Laravel\Prompts\info("{$successMessagePrefix}: {$name}");
		\Laravel\Prompts\note("Path: {$filePath}");

		return Command::SUCCESS;
	}
}
