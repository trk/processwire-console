<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Support\FeatureDiscoverer;
use Totoglu\Console\Traits\InteractWithProcessWire;

final class TestE2eCommand extends Command
{
	use InteractWithProcessWire;

	/** @var list<string> */
	private const PLAYWRIGHT_VALUE_FLAGS = [
		'-c',
		'--config',
		'-g',
		'--grep',
		'-gv',
		'--grep-invert',
		'-j',
		'--workers',
		'--max-failures',
		'--output',
		'--project',
		'--repeat-each',
		'--reporter',
		'--retries',
		'--shard',
		'--timeout',
		'--tsconfig',
		'--ui-host',
		'--ui-port',
	];

	protected function configure(): void
	{
		$this
			->setName('test:e2e')
			->setDescription('Run end-to-end browser tests using Playwright.')
			->addOption('init', null, InputOption::VALUE_NONE, 'Scaffold Playwright configuration and ProcessWire E2E helpers without running tests')
			->addOption('module', 'm', InputOption::VALUE_REQUIRED, 'Run E2E specs only for a specific installed module')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output')
			->ignoreValidationErrors();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$argv = $_SERVER['argv'] ?? [];
		$parsedFlags = self::parseConsoleFlags($input, $argv);
		$asJson = $parsedFlags['json'];
		$initOnly = $parsedFlags['init'];
		$module = $parsedFlags['module'];

		if ($module !== null && !$this->isValidIdentifier($module)) {
			return $this->failCommand(
				$output,
				$asJson,
				'INVALID_MODULE',
				"Invalid module name '{$module}'. Must match /^[A-Za-z][A-Za-z0-9_]*$/."
			);
		}

		$wire = class_exists('\\ProcessWire\\ProcessWire', false) ? \ProcessWire\ProcessWire::getCurrentInstance() : null;
		$config = $wire !== null ? \ProcessWire\wire('config') : null;
		if (!($wire instanceof \ProcessWire\ProcessWire) || !($config instanceof \ProcessWire\Config)) {
			return $this->failCommand($output, $asJson, 'ENV_NOT_FOUND', 'ProcessWire environment not found.');
		}
		$sitePath = rtrim((string) $config->paths->site, '/');
		$projectRoot = dirname($sitePath);
		$playwrightBinary = self::resolvePlaywrightBinaryPath($projectRoot);
		$baseUrl = self::resolveBaseUrl($config);
		$rawAdminUrl = trim((string) ($config->urls->admin ?? '/processwire/'), '/');
		$adminUrl = $rawAdminUrl !== '' ? '/' . $rawAdminUrl . '/' : '/processwire/';

		if ($initOnly) {
			return $this->handleInitOnly($projectRoot, $playwrightBinary, $baseUrl, $adminUrl, $asJson, $input->isInteractive(), $output);
		}

		if (!file_exists($playwrightBinary)) {
			if ($asJson || !$input->isInteractive()) {
				return $this->failCommand(
					$output,
					$asJson,
					'PLAYWRIGHT_NOT_FOUND',
					'Playwright binary not found at node_modules/.bin/playwright. Install it with `npm install -D @playwright/test @axe-core/playwright` or run `php vendor/bin/wire test:e2e --init`.'
				);
			}

			$install = \Laravel\Prompts\confirm(
				label: 'Playwright is not installed. Would you like to install @playwright/test and @axe-core/playwright via npm now?',
				default: true
			);

			if (!$install) {
				return Command::SUCCESS;
			}

			if (!$this->installPlaywrightPackages($projectRoot)) {
				return Command::FAILURE;
			}

			if ($this->scaffoldPlaywrightFiles($projectRoot, $baseUrl, $adminUrl) === null) {
				\Laravel\Prompts\error('Failed to load Playwright stubs.');
				return Command::FAILURE;
			}
		} elseif (!is_file($projectRoot . '/playwright.config.ts')) {
			if ($asJson || !$input->isInteractive()) {
				return $this->failCommand(
					$output,
					$asJson,
					'PLAYWRIGHT_CONFIG_MISSING',
					'playwright.config.ts not found at project root. Run `php vendor/bin/wire test:e2e --init` to scaffold ProcessWire E2E configuration.'
				);
			}

			$scaffold = \Laravel\Prompts\confirm(
				label: 'playwright.config.ts is missing. Would you like to scaffold ProcessWire Playwright configuration and helpers now?',
				default: true
			);

			if (!$scaffold) {
				return Command::SUCCESS;
			}

			$result = $this->scaffoldPlaywrightFiles($projectRoot, $baseUrl, $adminUrl);
			if ($result === null) {
				\Laravel\Prompts\error('Failed to load Playwright stubs.');
				return Command::FAILURE;
			}
			\Laravel\Prompts\note('Scaffolded Playwright E2E configuration in playwright.config.ts and tests/e2e/.');
		}

		$forwardArgs = self::extractForwardArgs($_SERVER['argv'] ?? []);
		$hasExplicitPath = self::hasExplicitPathFilter($forwardArgs);
		$executionPaths = [];

		if ($module !== null) {
			$resolvedModuleDir = self::resolveInstalledModuleDirectory($wire, $module);
			if ($resolvedModuleDir === null) {
				return $this->failCommand($output, $asJson, 'MODULE_NOT_INSTALLED', "Module '{$module}' is not installed in ProcessWire.");
			}

			$moduleE2eDir = rtrim($resolvedModuleDir, '/') . '/tests/e2e/';
			if (!self::directoryHasSpecFiles($moduleE2eDir)) {
				$folderName = basename(rtrim($resolvedModuleDir, '/'));
				$msg = "No Playwright E2E specs found for module '{$module}' in site/modules/{$folderName}/tests/e2e/.";
				if ($asJson) {
					return $this->outputJsonError($output, 'NO_E2E_SPECS', $msg);
				}
				\Laravel\Prompts\warning($msg);
				return Command::FAILURE;
			}

			$executionPaths[] = $moduleE2eDir;
		} elseif (!$hasExplicitPath) {
			$discoverer = new FeatureDiscoverer($wire);
			$candidates = array_merge(
				[
					$projectRoot . '/tests/e2e/',
					rtrim((string) $config->paths->templates, '/') . '/tests/e2e/',
				],
				$discoverer->discoverPaths('tests/e2e')
			);

			$seen = [];
			foreach ($candidates as $candidateDir) {
				$normalized = rtrim(str_replace('\\', '/', $candidateDir), '/') . '/';
				if (isset($seen[$normalized])) {
					continue;
				}
				$seen[$normalized] = true;

				if (self::directoryHasSpecFiles($normalized)) {
					$executionPaths[] = $normalized;
				}
			}

			if ($executionPaths === [] && $forwardArgs === []) {
				$msg = 'No Playwright E2E spec files (*.spec.ts) found. Create one with `php vendor/bin/wire make:test HomeFlow --e2e`.';
				if ($asJson) {
					return $this->outputJsonError($output, 'NO_E2E_SPECS', $msg);
				}
				\Laravel\Prompts\warning($msg);
				return Command::SUCCESS;
			}
		}

		$commandParts = [escapeshellarg($playwrightBinary), 'test'];

		if (!$hasExplicitPath && $executionPaths !== []) {
			$normalizedProjectPrefix = rtrim(str_replace('\\', '/', $projectRoot), '/') . '/';
			foreach ($executionPaths as $absPath) {
				$normalizedAbs = rtrim(str_replace('\\', '/', $absPath), '/');
				if (str_starts_with($normalizedAbs, $normalizedProjectPrefix)) {
					$relativePath = substr($normalizedAbs, strlen($normalizedProjectPrefix));
				} else {
					$relativePath = $normalizedAbs;
				}
				$commandParts[] = escapeshellarg($relativePath);
			}
		}

		foreach ($forwardArgs as $arg) {
			$commandParts[] = escapeshellarg($arg);
		}

		$commandStr = implode(' ', $commandParts);
		$currentDir = getcwd() ?: $projectRoot;
		$prevBaseUrl = getenv('PW_BASE_URL');
		$prevAdminUrl = getenv('PW_ADMIN_URL');

		if ($prevBaseUrl === false || $prevBaseUrl === '') {
			putenv("PW_BASE_URL={$baseUrl}");
		}
		if ($prevAdminUrl === false || $prevAdminUrl === '') {
			putenv("PW_ADMIN_URL={$adminUrl}");
		}

		chdir($projectRoot);
		$exitCode = 0;

		try {
			if ($asJson) {
				$lines = [];
				exec($commandStr . ' 2>&1', $lines, $exitCode);
				$joined = implode("\n", $lines);
				if ($exitCode !== 0) {
					return $this->outputJsonError($output, 'E2E_TESTS_FAILED', $joined);
				}
				return $this->outputJsonSuccess($output, [
					'command' => $commandStr,
					'baseUrl' => $prevBaseUrl !== false && $prevBaseUrl !== '' ? $prevBaseUrl : $baseUrl,
					'module' => $module,
					'exitCode' => $exitCode,
					'output' => $joined,
				]);
			}

			passthru($commandStr, $exitCode);
			return $exitCode;
		} finally {
			chdir($currentDir);
			if ($prevBaseUrl === false) {
				putenv('PW_BASE_URL');
			} else {
				putenv("PW_BASE_URL={$prevBaseUrl}");
			}
			if ($prevAdminUrl === false) {
				putenv('PW_ADMIN_URL');
			} else {
				putenv("PW_ADMIN_URL={$prevAdminUrl}");
			}
		}
	}

	private function handleInitOnly(
		string $projectRoot,
		string $playwrightBinary,
		string $baseUrl,
		string $adminUrl,
		bool $asJson,
		bool $isInteractive,
		OutputInterface $output
	): int {
		if (!file_exists($playwrightBinary)) {
			if ($asJson || !$isInteractive) {
				return $this->failCommand(
					$output,
					$asJson,
					'PLAYWRIGHT_NOT_FOUND',
					'Playwright binary not found at node_modules/.bin/playwright. Run `npm install -D @playwright/test @axe-core/playwright` first.'
				);
			}

			$install = \Laravel\Prompts\confirm(
				label: 'Playwright is not installed. Would you like to install @playwright/test and @axe-core/playwright via npm now?',
				default: true
			);

			if ($install && !$this->installPlaywrightPackages($projectRoot)) {
				return Command::FAILURE;
			}
		}

		$result = $this->scaffoldPlaywrightFiles($projectRoot, $baseUrl, $adminUrl);
		if ($result === null) {
			return $this->failCommand($output, $asJson, 'STUB_NOT_FOUND', 'Failed to load or write Playwright scaffolding stubs.');
		}

		if ($asJson) {
			return $this->outputJsonSuccess($output, [
				'initialized' => true,
				'baseUrl' => $baseUrl,
				'adminUrl' => $adminUrl,
				'created' => $result['created'],
				'skipped' => $result['skipped'],
			]);
		}

		\Laravel\Prompts\info('Playwright E2E environment initialized.');
		if ($result['created'] !== []) {
			\Laravel\Prompts\note("Created:\n- " . implode("\n- ", $result['created']));
		}
		if ($result['skipped'] !== []) {
			\Laravel\Prompts\note("Preserved existing:\n- " . implode("\n- ", $result['skipped']));
		}

		return Command::SUCCESS;
	}

	private function installPlaywrightPackages(string $projectRoot): bool
	{
		$currentDir = getcwd() ?: $projectRoot;
		chdir($projectRoot);

		try {
			if (!is_file($projectRoot . '/package.json')) {
				\Laravel\Prompts\info('Initializing package.json...');
				$initCode = 0;
				passthru('npm init -y', $initCode);
				if ($initCode !== 0) {
					\Laravel\Prompts\error('Failed to initialize package.json via npm.');
					return false;
				}
			}

			\Laravel\Prompts\info('Installing @playwright/test and @axe-core/playwright...');
			$installCode = 0;
			passthru('npm install -D @playwright/test @axe-core/playwright', $installCode);
			if ($installCode !== 0) {
				\Laravel\Prompts\error('Failed to install Playwright npm packages. Please install manually.');
				return false;
			}

			$installBrowsers = \Laravel\Prompts\confirm(
				label: 'Would you also like to download the Playwright Chromium browser binary now (`npx playwright install chromium`)?',
				default: true
			);
			if ($installBrowsers) {
				$browserCode = 0;
				passthru('npx playwright install chromium', $browserCode);
			}

			return true;
		} finally {
			chdir($currentDir);
		}
	}

	/**
	 * Non-destructively scaffold Playwright config and ProcessWire E2E setup/helper files.
	 *
	 * @return array{created: list<string>, skipped: list<string>}|null
	 */
	public function scaffoldPlaywrightFiles(string $projectRoot, string $baseUrl, string $adminUrl): ?array
	{
		$replacements = [
			'{{baseUrl}}' => addcslashes(str_replace(["\r", "\n"], '', $baseUrl), "\\'"),
			'{{adminUrl}}' => addcslashes(str_replace(["\r", "\n"], '', $adminUrl), "\\'"),
		];

		$targets = [
			'playwright.config.ts' => 'playwright.config.stub',
			'tests/e2e/global-setup.ts' => 'playwright.global-setup.stub',
			'tests/e2e/global-teardown.ts' => 'playwright.global-teardown.stub',
			'tests/e2e/helpers.ts' => 'playwright.helpers.stub',
		];

		$renderedMap = [];
		foreach ($targets as $relativeTarget => $stubName) {
			$rendered = $this->loadStub($stubName, $replacements);
			if ($rendered === null) {
				return null;
			}
			$renderedMap[$relativeTarget] = $rendered;
		}

		$created = [];
		$skipped = [];

		foreach ($renderedMap as $relativeTarget => $content) {
			$fullPath = rtrim($projectRoot, '/') . '/' . $relativeTarget;
			if (file_exists($fullPath) || is_link($fullPath)) {
				$skipped[] = $relativeTarget;
				continue;
			}

			$parentDir = dirname($fullPath);
			if (is_link($parentDir) || (!is_dir($parentDir) && !mkdir($parentDir, 0755, true) && !is_dir($parentDir))) {
				return null;
			}

			if (file_put_contents($fullPath, $content) === false) {
				return null;
			}
			$created[] = $relativeTarget;
		}

		$gitignorePath = rtrim($projectRoot, '/') . '/.gitignore';
		if (is_file($gitignorePath) && !is_link($gitignorePath) && is_writable($gitignorePath)) {
			$gitignoreContent = (string) file_get_contents($gitignorePath);
			$entriesToEnsure = ['/playwright-report', '/test-results', '/tests/e2e/.auth'];
			$missingEntries = [];
			foreach ($entriesToEnsure as $entry) {
				if (!str_contains($gitignoreContent, $entry)) {
					$missingEntries[] = $entry;
				}
			}
			if ($missingEntries !== []) {
				$separator = ($gitignoreContent === '' || str_ends_with($gitignoreContent, "\n")) ? '' : "\n";
				file_put_contents($gitignorePath, $gitignoreContent . $separator . implode("\n", $missingEntries) . "\n");
			}
		}

		return [
			'created' => $created,
			'skipped' => $skipped,
		];
	}

	/**
	 * Resolve the local Playwright CLI executable path across POSIX and Windows environments.
	 */
	public static function resolvePlaywrightBinaryPath(string $projectRoot): string
	{
		$suffix = DIRECTORY_SEPARATOR === '\\' ? '.cmd' : '';
		return rtrim($projectRoot, '/\\') . DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR . '.bin' . DIRECTORY_SEPARATOR . 'playwright' . $suffix;
	}

	/**
	 * Resolve the primary application base URL from environment or ProcessWire configuration.
	 */
	public static function resolveBaseUrl(?object $config): string
	{
		$envUrl = getenv('PW_BASE_URL');
		if (is_string($envUrl) && trim($envUrl) !== '') {
			return rtrim(trim($envUrl), '/');
		}

		$host = 'localhost';
		$https = false;

		if (is_object($config)) {
			$hosts = isset($config->httpHosts) && is_array($config->httpHosts)
				? array_values(array_filter($config->httpHosts, static fn(mixed $h): bool => is_string($h) && trim($h) !== ''))
				: [];

			if ($hosts !== []) {
				$host = trim((string) $hosts[0]);
			} elseif (isset($config->httpHost) && is_string($config->httpHost) && trim($config->httpHost) !== '') {
				$host = trim($config->httpHost);
			}

			$https = !empty($config->https);
		}

		$isLoopback = $host === 'localhost'
			|| $host === '127.0.0.1'
			|| str_starts_with($host, 'localhost:')
			|| str_starts_with($host, '127.0.0.1:');

		$scheme = ($https || !$isLoopback) ? 'https' : 'http';

		return "{$scheme}://{$host}";
	}

	/**
	 * Parse console-owned flags (--json, --init, --module) reliably even when ignoreValidationErrors()
	 * encounters unrecognized Playwright flags earlier in argv.
	 *
	 * @param array<int, mixed> $argv
	 * @return array{json: bool, init: bool, module: ?string}
	 */
	public static function parseConsoleFlags(InputInterface $input, array $argv): array
	{
		$asJson = (bool) $input->getOption('json');
		$initOnly = (bool) $input->getOption('init');
		$rawModule = $input->getOption('module');
		$module = ($rawModule !== null && $rawModule !== '') ? (string) $rawModule : null;

		$foundCommand = false;
		$count = count($argv);
		for ($i = 0; $i < $count; $i++) {
			$arg = (string) $argv[$i];
			if (!$foundCommand) {
				if ($arg === 'test:e2e') {
					$foundCommand = true;
				}
				continue;
			}

			if ($arg === '--json') {
				$asJson = true;
			} elseif ($arg === '--init') {
				$initOnly = true;
			} elseif (str_starts_with($arg, '--module=')) {
				$module = substr($arg, 9);
			} elseif (str_starts_with($arg, '-m=')) {
				$module = substr($arg, 3);
			} elseif ($arg === '--module' || $arg === '-m') {
				if (isset($argv[$i + 1]) && !str_starts_with((string) $argv[$i + 1], '-')) {
					$module = (string) $argv[$i + 1];
					$i++;
				} else {
					$module = '';
				}
			}
		}

		return [
			'json' => $asJson,
			'init' => $initOnly,
			'module' => $module,
		];
	}

	/**
	 * Resolve an installed ProcessWire module's root directory by folder name or module class name.
	 */
	private static function resolveInstalledModuleDirectory(\ProcessWire\ProcessWire $wire, string $moduleName): ?string
	{
		$siteModulesRoot = rtrim(str_replace('\\', '/', (string) $wire->config->paths->siteModules), '/') . '/';
		$candidateFolder = $siteModulesRoot . $moduleName;

		if (
			is_dir($candidateFolder)
			&& FeatureDiscoverer::isModuleDirectoryActive(
				$candidateFolder,
				$moduleName,
				static fn(string $name): bool => (bool) $wire->modules->isInstalled($name)
			)
		) {
			return $candidateFolder . '/';
		}

		if ($wire->modules->isInstalled($moduleName)) {
			$moduleFile = (string) $wire->modules->getModuleFile($moduleName);
			if ($moduleFile !== '' && is_file($moduleFile)) {
				return rtrim(str_replace('\\', '/', dirname($moduleFile)), '/') . '/';
			}
		}

		return null;
	}

	/**
	 * Extract Playwright-native CLI arguments from raw argv while stripping console-specific flags.
	 *
	 * @param array<int, mixed> $argv
	 * @return list<string>
	 */
	public static function extractForwardArgs(array $argv): array
	{
		$forwardArgs = [];
		$foundCommand = false;
		$skipNext = false;

		foreach ($argv as $rawArg) {
			$arg = (string) $rawArg;

			if (!$foundCommand) {
				if ($arg === 'test:e2e') {
					$foundCommand = true;
				}
				continue;
			}

			if ($skipNext) {
				$skipNext = false;
				if (!str_starts_with($arg, '-')) {
					continue;
				}
			}

			if (in_array($arg, ['--json', '--init', '--no-interaction', '-n', '--ansi', '--no-ansi', '-q', '--quiet'], true)) {
				continue;
			}

			if ($arg === '--module' || $arg === '-m') {
				$skipNext = true;
				continue;
			}

			if (str_starts_with($arg, '--module=') || str_starts_with($arg, '-m=')) {
				continue;
			}

			$forwardArgs[] = $arg;
		}

		return $forwardArgs;
	}

	/**
	 * Check whether forwarded Playwright arguments already include an explicit positional path/pattern filter.
	 *
	 * @param list<string> $forwardArgs
	 */
	public static function hasExplicitPathFilter(array $forwardArgs): bool
	{
		$skipNext = false;

		foreach ($forwardArgs as $arg) {
			if ($skipNext) {
				$skipNext = false;
				continue;
			}

			if ($arg === '--') {
				continue;
			}

			if (in_array($arg, self::PLAYWRIGHT_VALUE_FLAGS, true)) {
				$skipNext = true;
				continue;
			}

			if (!str_starts_with($arg, '-')) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Determine whether a directory recursively contains at least one Playwright spec/test file.
	 */
	public static function directoryHasSpecFiles(string $directory): bool
	{
		if (!is_dir($directory)) {
			return false;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
		);

		/** @var SplFileInfo $file */
		foreach ($iterator as $file) {
			if (!$file->isFile()) {
				continue;
			}
			$filename = $file->getFilename();
			if (
				str_ends_with($filename, '.spec.ts')
				|| str_ends_with($filename, '.spec.js')
				|| str_ends_with($filename, '.test.ts')
				|| str_ends_with($filename, '.test.js')
			) {
				return true;
			}
		}

		return false;
	}
}
