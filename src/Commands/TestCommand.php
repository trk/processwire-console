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
		$asJson = (bool) $input->getOption('json');
		$config = \ProcessWire\wire('config');
		$projectRoot = dirname(rtrim($config->paths->site, '/'));
		$pestBinary = $projectRoot . '/vendor/bin/pest';

		if (!file_exists($pestBinary)) {
			if ($asJson) {
				return $this->outputJsonError($output, 'PEST_NOT_FOUND', 'Pest binary not found at vendor/bin/pest.');
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
					mkdir($projectRoot . '/tests');
				}

				// Init Pest
				passthru(escapeshellarg($pestBinary) . ' --init');

				// Inject ProcessWire Bootstrap to Pest.php if it exists
				$pestConfigFile = $projectRoot . '/tests/Pest.php';
				$bootstrapCode = "\n/*\n|--------------------------------------------------------------------------\n| ProcessWire Bootstrap\n|--------------------------------------------------------------------------\n|\n| Boot processwire to make `wire()` and \$pages available in all tests.\n|\n*/\nif (!class_exists('\\\\ProcessWire\\\\ProcessWire')) {\n\trequire_once dirname(__DIR__) . '/index.php';\n}\n";
				$pestConfigContent = file_get_contents($pestConfigFile);
				if ($pestConfigContent !== false && !str_contains($pestConfigContent, 'class_exists(\'\\ProcessWire')) {
					file_put_contents($pestConfigFile, $pestConfigContent . $bootstrapCode);
					\Laravel\Prompts\note('processwire-console automatically injected ProcessWire bootstrap into tests/Pest.php');
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

		$argv = $_SERVER['argv'] ?? [];
		$forwardArgs = [];
		$foundTest = false;

		foreach ($argv as $arg) {
			if ($foundTest) {
				if ($arg === '--json') {
					continue;
				}
				$forwardArgs[] = $arg;
			} elseif ($arg === 'test') {
				$foundTest = true;
			}
		}

		$hasPathArg = false;
		foreach ($forwardArgs as $arg) {
			if (!str_starts_with($arg, '-')) {
				$hasPathArg = true;
				break;
			}
		}

		$commandParts = [escapeshellarg($pestBinary)];

		if (!$hasPathArg && !empty($executionPaths)) {
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

		if ($asJson) {
			$lines = [];
			exec($commandStr . ' 2>&1', $lines, $exitCode);
			chdir($currentDir);
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
		chdir($currentDir);

		return $exitCode;
	}
}
