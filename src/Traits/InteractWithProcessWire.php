<?php

declare(strict_types=1);

namespace Totoglu\Console\Traits;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\note;
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
		return (string) search(
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
		return (string) search(
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
		return (string) search(
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
		return (string) search(
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
		return (string) search(
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
		$options = array_map('strval', array_keys($logs));

		if ($options === []) {
			return [];
		}

		$selected = multiselect(
			label: $label,
			options: $options,
			hint: 'Use Space to select, Enter to confirm.'
		);

		return array_values(array_map('strval', $selected));
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
	 * Output an error in either JSON or interactive CLI format and return Command::FAILURE.
	 */
	protected function failCommand(OutputInterface $output, bool $asJson, string $code, string $message): int
	{
		if ($asJson) {
			return $this->outputJsonError($output, $code, $message);
		}

		error($message);
		return Command::FAILURE;
	}

	/**
	 * Determine whether the command may display interactive prompts.
	 */
	protected function canPrompt(InputInterface $input, bool $asJson, bool $force = false): bool
	{
		return $input->isInteractive() && !$asJson && !$force;
	}

	/**
	 * Determine whether a ProcessWire Page is a protected core/system page (root, admin, trash, 404, RBAC containers, or statusSystem).
	 */
	protected function isProtectedSystemPage(\ProcessWire\Page $page): bool
	{
		$id = (int) $page->id;
		if ($id <= 1) {
			return true;
		}

		$config = wire('config');
		$protectedIds = [
			1,
			(int) ($config->adminRootPageID ?? 2),
			(int) ($config->trashPageID ?? 7),
			(int) ($config->http404PageID ?? 27),
			(int) ($config->usersPageID ?? 29),
			(int) ($config->rolesPageID ?? 30),
			(int) ($config->permissionsPageID ?? 31),
		];

		if (in_array($id, $protectedIds, true)) {
			return true;
		}

		$systemMask = \ProcessWire\Page::statusSystemID | \ProcessWire\Page::statusSystem;
		return (((int) $page->status) & $systemMask) !== 0;
	}

	/**
	 * Check whether a ProcessWire selector string already defines a positive top-level `limit=N` clause.
	 */
	protected function selectorHasTopLevelLimit(string $selector): bool
	{
		$depth = 0;
		$quote = '';
		$current = '';
		$length = strlen($selector);

		for ($i = 0; $i < $length; $i++) {
			$ch = $selector[$i];
			if ($quote !== '') {
				if ($ch === '\\' && $i + 1 < $length) {
					$i++;
					continue;
				}
				if ($ch === $quote) {
					$quote = '';
				}
				continue;
			}

			if ($ch === '"' || $ch === "'") {
				$quote = $ch;
				continue;
			}
			if ($ch === '[') {
				$depth++;
				continue;
			}
			if ($ch === ']') {
				$depth = max(0, $depth - 1);
				continue;
			}

			if ($depth === 0) {
				if ($ch === ',') {
					if (preg_match('/^\s*limit\s*=\s*([1-9][0-9]*)\s*$/i', $current) === 1) {
						return true;
					}
					$current = '';
					continue;
				}
				$current .= $ch;
			}
		}

		return preg_match('/^\s*limit\s*=\s*([1-9][0-9]*)\s*$/i', $current) === 1;
	}

	/**
	 * Safely resolve a ProcessWire Page by numeric ID or leading-slash path without allowing raw selector injection.
	 */
	protected function resolvePage(int|string|null $id, ?string $path): ?\ProcessWire\Page
	{
		$pages = wire('pages');
		$sanitizer = wire('sanitizer');

		if ($id !== null && $id !== '') {
			$idStr = trim((string) $id);
			if (!ctype_digit($idStr)) {
				return null;
			}
			$page = $pages->get((int) $idStr);
			return ($page && $page->id) ? $page : null;
		}

		if ($path !== null && $path !== '') {
			$cleanPath = trim($path);
			if (!str_starts_with($cleanPath, '/')) {
				return null;
			}
			$safePath = $sanitizer->selectorValue($cleanPath);
			$page = $pages->get("path={$safePath}, include=all");
			return ($page && $page->id) ? $page : null;
		}

		return null;
	}

	/**
	 * Safely look up a ProcessWire named collection item (users, roles, permissions) by exact pageName without selector injection.
	 */
	protected function findNamedSystemPage(string $apiVar, string $rawName): ?\ProcessWire\Page
	{
		$sanitizer = wire('sanitizer');
		$clean = (string) $sanitizer->pageName(trim($rawName), true);
		if ($clean === '' || $clean !== strtolower(trim($rawName))) {
			// Also allow exact case-preserved pageName check
			$exactClean = (string) $sanitizer->pageName(trim($rawName));
			if ($exactClean === '' || $exactClean !== trim($rawName)) {
				return null;
			}
			$clean = $exactClean;
		}

		$safeVal = $sanitizer->selectorValue($clean);
		$item = wire($apiVar)->get("name={$safeVal}");

		return ($item instanceof \ProcessWire\Page && $item->id) ? $item : null;
	}

	/**
	 * Safely normalize and validate a ProcessWire log channel name, rejecting path separators and traversal tokens.
	 */
	protected function normalizeLogChannel(string $rawName): string
	{
		$trimmed = trim($rawName);
		if ($trimmed === '' || str_contains($trimmed, '/') || str_contains($trimmed, '\\') || str_contains($trimmed, '..')) {
			return '';
		}
		$base = (string) preg_replace('/\.txt$/i', '', $trimmed);
		if ($base === '' || $base === '.' || $base === '..') {
			return '';
		}
		if (preg_match('/^[A-Za-z0-9_-]+$/', $base) !== 1) {
			return '';
		}
		return $base;
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
		InputInterface $input,
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
			return $this->failCommand(
				$output,
				$asJson,
				'INVALID_NAME',
				"Invalid {$label} class name '{$name}'. Must match /^[A-Za-z][A-Za-z0-9_]*$/."
			);
		}

		if (!str_ends_with($name, $suffix)) {
			$name .= $suffix;
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

		$config = wire('config');
		if ($module !== null) {
			$targetDir = $config->paths->siteModules . $module . '/' . $directory . '/';
			$namespace = 'ProcessWire';
		} else {
			$targetDir = $config->paths->site . $directory . '/';
			$namespace = $defaultNamespace;
		}

		if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
			return $this->failCommand($output, $asJson, 'WRITE_FAILED', "Failed to create directory: {$targetDir}");
		}

		$filePath = $targetDir . $name . '.php';
		if (file_exists($filePath)) {
			$ucLabel = ucfirst($label);
			return $this->failCommand($output, $asJson, 'ALREADY_EXISTS', "{$ucLabel} file already exists: {$name}.php");
		}

		$content = $this->loadStub($stubFile, [
			'{{namespace}}' => $namespace,
			'{{class}}' => $name,
		]);
		if ($content === null) {
			return $this->failCommand($output, $asJson, 'STUB_NOT_FOUND', "Stub file not found: {$stubFile}");
		}

		if (file_put_contents($filePath, $content) === false) {
			return $this->failCommand($output, $asJson, 'WRITE_FAILED', "Failed to write file: {$filePath}");
		}

		if ($asJson) {
			return $this->outputJsonSuccess($output, [
				'class' => $name,
				'namespace' => $namespace,
				'path' => $filePath,
			]);
		}

		info("{$successMessagePrefix}: {$name}");
		note("Path: {$filePath}");

		return Command::SUCCESS;
	}
}
