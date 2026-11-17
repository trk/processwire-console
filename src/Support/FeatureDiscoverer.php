<?php

declare(strict_types=1);

namespace Totoglu\Console\Support;

use ProcessWire\ProcessWire;
use Symfony\Component\Console\Command\Command;

/**
 * FeatureDiscoverer
 *
 * Centralized service to discover project features (commands, seeders, tasks, queues, migrations, tests),
 * extract namespaces, resolve classes from files, and discover Composer package commands.
 */
final class FeatureDiscoverer
{
	public function __construct(
		private ProcessWire $wire
	) {
	}

	/**
	 * Get valid feature directory paths.
	 *
	 * @param string $feature Subdirectory name (e.g., 'commands', 'seeders', 'schedule', 'queue', 'migrations', 'tests')
	 * @return array<int, string> Valid absolute directory paths
	 */
	public function discoverPaths(string $feature): array
	{
		return self::discoverPathsFromRoots(
			(string) $this->wire->config->paths->site,
			(string) $this->wire->config->paths->siteModules,
			$feature,
			fn(string $moduleName): bool => (bool) $this->wire->modules->isInstalled($moduleName)
		);
	}

	/**
	 * Discover feature directories across site/ and installed site/modules/<Module>/.
	 *
	 * @param callable(string): bool $isModuleInstalled
	 * @return array<int, string>
	 */
	public static function discoverPathsFromRoots(
		string $siteRoot,
		string $siteModulesRoot,
		string $feature,
		callable $isModuleInstalled
	): array {
		$paths = [];
		$featureDir = trim(str_replace('\\', '/', $feature), '/');
		if ($featureDir === '') {
			return [];
		}

		$segmentCount = substr_count($featureDir, '/') + 1;
		$normalizedSiteRoot = rtrim(str_replace('\\', '/', $siteRoot), '/') . '/';
		$normalizedModulesRoot = rtrim(str_replace('\\', '/', $siteModulesRoot), '/') . '/';

		// 1. Root site feature path
		$sitePath = $normalizedSiteRoot . $featureDir . '/';
		if (is_dir($sitePath)) {
			$paths[] = $sitePath;
		}

		// 2. Module feature paths (only for installed modules)
		if (is_dir($normalizedModulesRoot)) {
			$matchedDirs = glob($normalizedModulesRoot . '*/' . $featureDir . '/', GLOB_ONLYDIR);
			if ($matchedDirs !== false) {
				foreach ($matchedDirs as $dir) {
					$dir = rtrim(str_replace('\\', '/', $dir), '/') . '/';
					$parts = explode('/', trim($dir, '/'));
					for ($i = 0; $i < $segmentCount; $i++) {
						array_pop($parts);
					}
					$moduleFolder = array_pop($parts);

					if ($moduleFolder !== null && $moduleFolder !== '' && self::isModuleDirectoryActive($normalizedModulesRoot . $moduleFolder, $moduleFolder, $isModuleInstalled)) {
						$paths[] = $dir;
					}
				}
			}
		}

		return $paths;
	}

	/**
	 * Check whether a module directory is installed directly by folder name or via any companion .module(.php) class inside it.
	 *
	 * @param callable(string): bool $isModuleInstalled
	 */
	public static function isModuleDirectoryActive(string $moduleDir, string $moduleFolder, callable $isModuleInstalled): bool
	{
		if ($isModuleInstalled($moduleFolder)) {
			return true;
		}

		$normalizedDir = rtrim(str_replace('\\', '/', $moduleDir), '/');
		$moduleFiles = array_merge(
			glob($normalizedDir . '/*.module.php') ?: [],
			glob($normalizedDir . '/*.module') ?: []
		);

		foreach ($moduleFiles as $moduleFile) {
			$className = (string) preg_replace('/\.module(\.php)?$/', '', basename($moduleFile));
			if ($className !== '' && $isModuleInstalled($className)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Discover files for a given feature folder matching a pattern.
	 *
	 * @param string $feature e.g., 'seeders'
	 * @param string $pattern Glob pattern within the folder (e.g., '*Seeder.php')
	 * @param string|null $stripExtension If provided, strips this extension from keys (e.g., '.php')
	 * @return array<string, string> Map of base names to their absolute paths
	 */
	public function discoverFiles(string $feature, string $pattern = '*.php', ?string $stripExtension = '.php'): array
	{
		$paths = $this->discoverPaths($feature);
		$filesMap = [];

		foreach ($paths as $path) {
			$foundFiles = glob($path . ltrim($pattern, '/'));
			if ($foundFiles !== false) {
				foreach ($foundFiles as $file) {
					$basename = $stripExtension !== null ? basename($file, $stripExtension) : basename($file);
					$filesMap[$basename] = $file;
				}
			}
		}

		ksort($filesMap);

		return $filesMap;
	}

	/**
	 * Extract the PHP namespace from a file.
	 * Supports both `namespace Foo\Bar;` and bracketed `namespace Foo\Bar {` syntax.
	 */
	public static function extractNamespace(string $filePath): ?string
	{
		if (!is_file($filePath) || !is_readable($filePath)) {
			return null;
		}

		$content = file_get_contents($filePath);
		if ($content === false) {
			return null;
		}

		if (preg_match('/^\s*namespace\s+([A-Za-z_][A-Za-z0-9_\\\\]*)\s*[;{]/m', $content, $matches)) {
			return trim($matches[1]);
		}

		return null;
	}

	/**
	 * Require a PHP file and resolve the fully-qualified class name for a given basename.
	 *
	 * @template T of object
	 * @param string $filePath Absolute path to the PHP file
	 * @param string $classBasename Short class name or FQCN
	 * @param class-string<T>|null $expectedSubclass Optional base class or interface to verify via is_subclass_of()
	 * @return ($expectedSubclass is null ? class-string|null : class-string<T>|null)
	 */
	public static function resolveClassFromFile(string $filePath, string $classBasename, ?string $expectedSubclass = null): ?string
	{
		if (!is_file($filePath)) {
			return null;
		}

		require_once $filePath;

		$shortName = basename(str_replace('\\', '/', $classBasename));
		$extractedNs = self::extractNamespace($filePath);

		$candidates = [];
		if (str_contains($classBasename, '\\')) {
			$candidates[] = ltrim($classBasename, '\\');
		}
		if ($extractedNs !== null && $extractedNs !== '') {
			$candidates[] = $extractedNs . '\\' . $shortName;
		}
		$candidates[] = $shortName;
		$candidates[] = 'ProcessWire\\' . $shortName;
		$candidates[] = 'Site\\Queue\\' . $shortName;
		$candidates[] = 'Site\\Seeders\\' . $shortName;
		$candidates[] = 'Site\\Schedule\\' . $shortName;

		foreach (array_unique($candidates) as $candidate) {
			if (preg_match('/^[A-Za-z_][A-Za-z0-9_\\\\]*$/', $candidate) !== 1) {
				continue;
			}
			if (class_exists($candidate)) {
				if ($expectedSubclass === null || is_subclass_of($candidate, $expectedSubclass)) {
					$ref = new \ReflectionClass($candidate);
					if ($ref->isAbstract() || $ref->isInterface() || $ref->isTrait()) {
						continue;
					}
					/** @var class-string<T> $candidate */
					return $candidate;
				}
			}
		}

		return null;
	}

	/**
	 * Discover all classes in a feature folder matching a file pattern and subclass contract.
	 *
	 * @template T of object
	 * @param string $feature Feature folder name (e.g., 'commands', 'seeders', 'schedule', 'queue')
	 * @param string $pattern File glob pattern (e.g., '*Command.php')
	 * @param class-string<T> $expectedSubclass Required parent class or interface
	 * @return array<string, class-string<T>> Map of [Basename => FQCN]
	 */
	public function discoverClasses(string $feature, string $pattern, string $expectedSubclass): array
	{
		$files = $this->discoverFiles($feature, $pattern, '.php');
		$classes = [];

		foreach ($files as $basename => $filePath) {
			$fqcn = self::resolveClassFromFile($filePath, $basename, $expectedSubclass);
			if ($fqcn !== null) {
				$classes[$basename] = $fqcn;
			}
		}

		return $classes;
	}

	/**
	 * Discover console commands declared by Composer packages in installed.json and root path repositories.
	 *
	 * @param string $vendorPath Path to the vendor directory (or project root containing vendor/)
	 * @return array<int, class-string<Command>>
	 */
	public static function discoverComposerCommands(string $vendorPath): array
	{
		$vendorPath = rtrim($vendorPath, '/');
		if (basename($vendorPath) === 'vendor') {
			$projectRoot = dirname($vendorPath);
			$vendorDir = $vendorPath;
		} else {
			$projectRoot = $vendorPath;
			$vendorDir = $vendorPath . '/vendor';
		}

		$discovered = [];

		// 1. vendor/composer/installed.json
		$installedJson = $vendorDir . '/composer/installed.json';
		if (is_file($installedJson)) {
			$jsonStr = file_get_contents($installedJson);
			if (is_string($jsonStr)) {
				$json = json_decode($jsonStr, true);
				if (is_array($json)) {
					$packages = array_key_exists('packages', $json) ? $json['packages'] : $json;
					if (is_array($packages)) {
						foreach ($packages as $pkg) {
							self::collectCommandClassesFromPackage($pkg, $discovered);
						}
					}
				}
			}
		}

		// 2. Path repositories in root composer.json
		$rootComposer = $projectRoot . '/composer.json';
		if (is_file($rootComposer)) {
			$rootCfg = json_decode((string) file_get_contents($rootComposer), true);
			$repos = is_array($rootCfg) ? ($rootCfg['repositories'] ?? []) : [];
			if (is_array($repos)) {
				foreach ($repos as $repo) {
					if (!is_array($repo) || ($repo['type'] ?? '') !== 'path') {
						continue;
					}
					$url = (string) ($repo['url'] ?? '');
					if ($url === '') {
						continue;
					}
					$relUrl = str_starts_with($url, './') ? substr($url, 2) : $url;
					$basePath = str_starts_with($url, '/') ? $url : $projectRoot . '/' . $relUrl;
					$composerFiles = str_contains($basePath, '*')
						? (glob(rtrim($basePath, '/') . '/composer.json') ?: [])
						: [rtrim($basePath, '/') . '/composer.json'];

					foreach ($composerFiles as $pkgComposer) {
						if (!is_file($pkgComposer)) {
							continue;
						}
						$pkgCfg = json_decode((string) file_get_contents($pkgComposer), true);
						self::collectCommandClassesFromPackage($pkgCfg, $discovered);
					}
				}
			}
		}

		return array_values(array_unique($discovered));
	}

	/**
	 * @param mixed $packageConfig
	 * @param array<int, class-string<Command>> $discovered
	 */
	private static function collectCommandClassesFromPackage(mixed $packageConfig, array &$discovered): void
	{
		if (!is_array($packageConfig)) {
			return;
		}

		$commands = $packageConfig['extra']['processwire-console']['commands'] ?? null;
		if (!is_array($commands)) {
			return;
		}

		foreach ($commands as $fqcn) {
			if (!is_string($fqcn) || preg_match('/^\\\\?[A-Za-z_][A-Za-z0-9_\\\\]*$/', $fqcn) !== 1) {
				continue;
			}
			if (class_exists($fqcn) && is_subclass_of($fqcn, Command::class)) {
				$ref = new \ReflectionClass($fqcn);
				if (!$ref->isAbstract()) {
					$discovered[] = $fqcn;
				}
			}
		}
	}

	/**
	 * Resolve validated MySQL/MariaDB storage engine and character set from ProcessWire config.
	 *
	 * @return array{engine: string, charset: string}
	 */
	public static function resolveStorageTableOptions(?object $config = null): array
	{
		$cfg = $config ?? (function_exists('\ProcessWire\wire') ? \ProcessWire\wire('config') : null);
		$engine = is_object($cfg) && !empty($cfg->dbEngine) ? (string) $cfg->dbEngine : 'InnoDB';
		$charset = is_object($cfg) && !empty($cfg->dbCharset) ? (string) $cfg->dbCharset : 'utf8mb4';

		$allowedEngines = ['InnoDB', 'MyISAM', 'Aria', 'MEMORY', 'ARCHIVE'];
		if (!in_array($engine, $allowedEngines, true)) {
			$engine = 'InnoDB';
		}

		if (!preg_match('/^[a-zA-Z0-9_]+$/', $charset)) {
			$charset = 'utf8mb4';
		}

		return ['engine' => $engine, 'charset' => $charset];
	}

	/**
	 * Read the last N lines of a file in reverse 8KB chunks without loading the entire file into memory.
	 *
	 * @return list<string>
	 */
	public static function readLastLines(string $filePath, int $maxLines): array
	{
		if ($maxLines <= 0) {
			return [];
		}

		$handle = @fopen($filePath, 'rb');
		if ($handle === false) {
			return [];
		}

		try {
			fseek($handle, 0, SEEK_END);
			$pos = ftell($handle);
			if ($pos === false || $pos <= 0) {
				return [];
			}

			$buffer = '';
			$chunkSize = 8192;
			$newlineCount = 0;

			while ($pos > 0 && $newlineCount <= $maxLines) {
				$readSize = min($chunkSize, $pos);
				$pos -= $readSize;
				fseek($handle, $pos, SEEK_SET);
				$chunk = fread($handle, $readSize);
				if ($chunk === false) {
					break;
				}
				$buffer = $chunk . $buffer;
				$newlineCount = substr_count($buffer, "\n");
			}

			$buffer = rtrim($buffer, "\r\n");
			if ($buffer === '') {
				return [];
			}

			/** @var list<string> $lines */
			$lines = preg_split('/\r\n|\r|\n/', $buffer) ?: [];

			return array_slice($lines, -$maxLines);
		} finally {
			fclose($handle);
		}
	}
}
