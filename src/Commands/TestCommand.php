<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Support\FeatureDiscoverer;
use Totoglu\Console\Traits\InteractWithProcessWire;

final class TestCommand extends Command
{
	use InteractWithProcessWire;

	/** @var list<string> */
	private const PEST_VALUE_FLAGS = [
		'-c',
		'--configuration',
		'--bootstrap',
		'--cache-directory',
		'--filter',
		'--testsuite',
		'--group',
		'--exclude-group',
		'--covers',
		'--uses',
		'--process-isolation',
		'--order-by',
		'--random-order-seed',
		'--log-junit',
		'--log-teamcity',
		'--testdox-html',
		'--testdox-text',
		'--coverage-clover',
		'--coverage-cobertura',
		'--coverage-crap4j',
		'--coverage-html',
		'--coverage-php',
		'--coverage-text',
		'--coverage-xml',
		'--processes',
		'--shard',
	];

	protected function configure(): void
	{
		$this
			->setName('test')
			->setDescription('Run the application tests using Pest.')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output')
			->ignoreValidationErrors();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$asJson = (bool) $input->getOption('json') || in_array('--json', $_SERVER['argv'] ?? [], true);
		$config = \ProcessWire\wire('config');
		$projectRoot = dirname(rtrim($config->paths->site, '/'));
		$pestBinary = $projectRoot . '/vendor/bin/pest';

		if (!file_exists($pestBinary)) {
			if ($asJson || !$input->isInteractive()) {
				return $this->failCommand($output, $asJson, 'PEST_NOT_FOUND', 'Pest binary not found at vendor/bin/pest.');
			}

			$install = \Laravel\Prompts\confirm(
				label: 'Pest is not installed. Would you like to install it now via composer?',
				default: true
			);

			if ($install) {
				\Laravel\Prompts\info('Installing PestPHP...');
				$code = 0;
				passthru('composer require pestphp/pest --dev', $code);

				if ($code !== 0) {
					\Laravel\Prompts\error('Failed to install Pest. Please install manually.');
					return Command::FAILURE;
				}

				if (!is_dir($projectRoot . '/tests')) {
					mkdir($projectRoot . '/tests', 0755, true);
				}

				// Init Pest
				passthru(escapeshellarg($pestBinary) . ' --init');

				// Inject ProcessWire Bootstrap to Pest.php if it exists
				$pestConfigFile = $projectRoot . '/tests/Pest.php';
				if (is_file($pestConfigFile)) {
					$bootstrapCode = "\n/*\n|--------------------------------------------------------------------------\n| ProcessWire Bootstrap\n|--------------------------------------------------------------------------\n|\n| Boot ProcessWire in CLI mode so `wire()` and API variables are available.\n|\n*/\nif (!class_exists('\\\\ProcessWire\\\\ProcessWire')) {\n\t\$_SERVER['HTTP_HOST'] ??= 'localhost';\n\trequire_once dirname(__DIR__) . '/wire/core/ProcessWire.php';\n\tnew \\ProcessWire\\ProcessWire(\\ProcessWire\\ProcessWire::buildConfig(dirname(__DIR__)));\n}\n";
					$pestConfigContent = file_get_contents($pestConfigFile);
					if ($pestConfigContent !== false && !str_contains($pestConfigContent, 'ProcessWire::buildConfig') && !str_contains($pestConfigContent, 'class_exists(\'\\ProcessWire')) {
						file_put_contents($pestConfigFile, $pestConfigContent . $bootstrapCode);
						\Laravel\Prompts\note('processwire-console automatically injected ProcessWire bootstrap into tests/Pest.php');
					}
				}
			} else {
				return Command::SUCCESS;
			}
		}

		$discoverer = new FeatureDiscoverer(\ProcessWire\wire());
		$moduleTestPaths = $discoverer->discoverPaths('tests');

		$executionPaths = [];

		$rootTestsDir = $projectRoot . '/tests/';
		if (is_dir($rootTestsDir)) {
			$executionPaths[] = $rootTestsDir;
		}

		foreach ($moduleTestPaths as $mPath) {
			$executionPaths[] = $mPath;
		}

		$forwardArgs = self::extractForwardArgs($_SERVER['argv'] ?? []);
		$hasPathArg = self::hasExplicitPathFilter($forwardArgs);

		$commandParts = [escapeshellarg($pestBinary)];

		if (!$hasPathArg && $executionPaths !== []) {
			foreach ($executionPaths as $path) {
				$relativePath = str_replace($projectRoot . '/', '', $path);
				$commandParts[] = escapeshellarg($relativePath);
			}
		}

		foreach ($forwardArgs as $arg) {
			$commandParts[] = escapeshellarg($arg);
		}

		$commandStr = implode(' ', $commandParts);
		$exitCode = 0;
		$currentDir = getcwd() ?: $projectRoot;
		chdir($projectRoot);

		try {
			if ($asJson) {
				$lines = [];
				exec($commandStr . ' 2>&1', $lines, $exitCode);
				$payload = [
					'command' => $commandStr,
					'exitCode' => $exitCode,
					'output' => implode("\n", $lines),
				];
				if ($exitCode !== 0) {
					return $this->outputJsonError($output, 'TESTS_FAILED', implode("\n", $lines));
				}
				return $this->outputJsonSuccess($output, $payload);
			}

			passthru($commandStr, $exitCode);

			return $exitCode;
		} finally {
			chdir($currentDir);
		}
	}

	/**
	 * Extract Pest/PHPUnit-native CLI arguments from raw argv while stripping console-only flags.
	 *
	 * @param array<int, mixed> $argv
	 * @return list<string>
	 */
	public static function extractForwardArgs(array $argv): array
	{
		$forwardArgs = [];
		$foundTest = false;

		foreach ($argv as $rawArg) {
			$arg = (string) $rawArg;
			if (!$foundTest) {
				if ($arg === 'test') {
					$foundTest = true;
				}
				continue;
			}

			if (in_array($arg, ['--json', '--no-interaction', '-n', '--ansi', '--no-ansi', '-q', '--quiet'], true)) {
				continue;
			}

			$forwardArgs[] = $arg;
		}

		return $forwardArgs;
	}

	/**
	 * Check whether forwarded Pest arguments include an explicit positional path/file target.
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

			if (in_array($arg, self::PEST_VALUE_FLAGS, true)) {
				$skipNext = true;
				continue;
			}

			if (!str_starts_with($arg, '-')) {
				return true;
			}
		}

		return false;
	}
}
