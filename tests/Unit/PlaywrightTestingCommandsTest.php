<?php

declare(strict_types=1);

namespace Totoglu\Console\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Totoglu\Console\Commands\MakeTestCommand;
use Totoglu\Console\Commands\TestE2eCommand;

final class PlaywrightTestingCommandsTest extends TestCase
{
	private string $tempDir;

	protected function setUp(): void
	{
		parent::setUp();
		$this->tempDir = sys_get_temp_dir() . '/pw_e2e_cmd_test_' . uniqid('', true);
		mkdir($this->tempDir, 0777, true);
	}

	protected function tearDown(): void
	{
		$this->removeDirectory($this->tempDir);
		parent::tearDown();
	}

	private function removeDirectory(string $dir): void
	{
		if (!is_dir($dir)) {
			return;
		}
		$items = scandir($dir);
		if ($items === false) {
			return;
		}
		foreach ($items as $item) {
			if ($item === '.' || $item === '..') {
				continue;
			}
			$path = $dir . '/' . $item;
			if (is_dir($path)) {
				$this->removeDirectory($path);
			} else {
				@unlink($path);
			}
		}
		@rmdir($dir);
	}

	public function testMakeTestCommandNormalizesE2eSlugsAndRelativeImports(): void
	{
		$this->assertSame('workspace-flow', MakeTestCommand::normalizeE2eSpecSlug('WorkspaceFlow'));
		$this->assertSame('workspace-flow', MakeTestCommand::normalizeE2eSpecSlug('WorkspaceFlowTest'));
		$this->assertSame('workspace-flow', MakeTestCommand::normalizeE2eSpecSlug('WorkspaceFlowSpec'));
		$this->assertSame('seo-workspace', MakeTestCommand::normalizeE2eSpecSlug('SEOWorkspace.spec.ts'));
		$this->assertSame('entries-lifecycle', MakeTestCommand::normalizeE2eSpecSlug('entries_lifecycle'));
		$this->assertSame('test', MakeTestCommand::normalizeE2eSpecSlug('Test'));
		$this->assertNull(MakeTestCommand::normalizeE2eSpecSlug('123Invalid'));
		$this->assertNull(MakeTestCommand::normalizeE2eSpecSlug('../escape'));

		$this->assertSame(
			'../../../tests/e2e/helpers',
			MakeTestCommand::computeRelativeImportPath('/var/www/site/tests/e2e/', '/var/www/tests/e2e/helpers')
		);
		$this->assertSame(
			'../../../../../tests/e2e/helpers',
			MakeTestCommand::computeRelativeImportPath('/var/www/site/modules/WireEntries/tests/e2e/', '/var/www/tests/e2e/helpers')
		);
	}

	public function testMakeTestCommandRejectsCombinedUnitAndE2eFlags(): void
	{
		$command = new MakeTestCommand();
		$input = new ArrayInput([
			'name' => 'WorkspaceFlow',
			'--unit' => true,
			'--e2e' => true,
			'--json' => true,
		]);
		$output = new BufferedOutput();

		$exitCode = $command->run($input, $output);
		$this->assertSame(Command::FAILURE, $exitCode);

		$decoded = json_decode($output->fetch(), true);
		$this->assertIsArray($decoded);
		$this->assertFalse($decoded['ok']);
		$this->assertSame('INVALID_OPTIONS', $decoded['error']['code']);
	}

	public function testTestE2eCommandResolvesBaseUrlAccurately(): void
	{
		$prevEnv = getenv('PW_BASE_URL');
		putenv('PW_BASE_URL');

		try {
			$vhostConfig = (object) [
				'httpHosts' => ['processwire.test', 'www.processwire.test', 'localhost'],
				'httpHost' => 'localhost',
				'https' => false,
			];
			$this->assertSame('https://processwire.test', TestE2eCommand::resolveBaseUrl($vhostConfig));

			$loopbackConfig = (object) [
				'httpHosts' => ['localhost:8080'],
				'httpHost' => 'localhost:8080',
				'https' => false,
			];
			$this->assertSame('http://localhost:8080', TestE2eCommand::resolveBaseUrl($loopbackConfig));

			$httpsLoopbackConfig = (object) [
				'httpHosts' => ['localhost'],
				'httpHost' => 'localhost',
				'https' => true,
			];
			$this->assertSame('https://localhost', TestE2eCommand::resolveBaseUrl($httpsLoopbackConfig));

			putenv('PW_BASE_URL=https://staging.example.com/');
			$this->assertSame('https://staging.example.com', TestE2eCommand::resolveBaseUrl($vhostConfig));
		} finally {
			if ($prevEnv === false) {
				putenv('PW_BASE_URL');
			} else {
				putenv("PW_BASE_URL={$prevEnv}");
			}
		}
	}

	public function testTestE2eCommandExtractsForwardArgsAndDetectsPathFilters(): void
	{
		$argv1 = ['vendor/bin/wire', 'test:e2e', '--module=Blog', '--json', '--no-interaction', '-n', '--grep', 'Workspace', '--headed'];
		$forward1 = TestE2eCommand::extractForwardArgs($argv1);
		$this->assertSame(['--grep', 'Workspace', '--headed'], $forward1);
		$this->assertFalse(TestE2eCommand::hasExplicitPathFilter($forward1));

		$argv2 = ['vendor/bin/wire', 'test:e2e', '-m', 'WireSeo', 'site/modules/WireSeo/tests/e2e/seo-workspace.spec.ts'];
		$forward2 = TestE2eCommand::extractForwardArgs($argv2);
		$this->assertSame(['site/modules/WireSeo/tests/e2e/seo-workspace.spec.ts'], $forward2);
		$this->assertTrue(TestE2eCommand::hasExplicitPathFilter($forward2));

		// Console flags after unrecognized Playwright flags must still parse reliably
		$command = new TestE2eCommand();
		$emptyInput = new ArrayInput([]);
		$emptyInput->bind($command->getDefinition());
		$flags = TestE2eCommand::parseConsoleFlags(
			$emptyInput,
			['vendor/bin/wire', 'test:e2e', '--list', '--module', 'WireConsole', '--json']
		);
		$this->assertSame([
			'json' => true,
			'init' => false,
			'module' => 'WireConsole',
		], $flags);
	}

	public function testScaffoldPlaywrightFilesIsNonDestructiveAndUpdatesGitignore(): void
	{
		file_put_contents($this->tempDir . '/.gitignore', "/vendor\n");

		$command = new TestE2eCommand();
		$firstRun = $command->scaffoldPlaywrightFiles($this->tempDir, 'https://processwire.test', '/61admin/');

		$this->assertIsArray($firstRun);
		$this->assertSame([
			'playwright.config.ts',
			'tests/e2e/global-setup.ts',
			'tests/e2e/global-teardown.ts',
			'tests/e2e/helpers.ts',
		], $firstRun['created']);
		$this->assertSame([], $firstRun['skipped']);

		$configContent = (string) file_get_contents($this->tempDir . '/playwright.config.ts');
		$this->assertStringContainsString("'https://processwire.test'", $configContent);

		$helpersContent = (string) file_get_contents($this->tempDir . '/tests/e2e/helpers.ts');
		$this->assertStringContainsString("'/61admin/'", $helpersContent);

		$gitignore = (string) file_get_contents($this->tempDir . '/.gitignore');
		$this->assertStringContainsString('/playwright-report', $gitignore);
		$this->assertStringContainsString('/test-results', $gitignore);
		$this->assertStringContainsString('/tests/e2e/.auth', $gitignore);

		// Helper-only directory has no spec files yet
		$this->assertFalse(TestE2eCommand::directoryHasSpecFiles($this->tempDir . '/tests/e2e'));

		// Custom edit must be preserved on subsequent --init runs
		file_put_contents($this->tempDir . '/playwright.config.ts', "// custom user config\n");
		$secondRun = $command->scaffoldPlaywrightFiles($this->tempDir, 'https://other.test', '/admin/');
		$this->assertIsArray($secondRun);
		$this->assertSame([], $secondRun['created']);
		$this->assertCount(4, $secondRun['skipped']);
		$this->assertSame("// custom user config\n", file_get_contents($this->tempDir . '/playwright.config.ts'));

		// Adding a .spec.ts file makes directoryHasSpecFiles return true
		file_put_contents($this->tempDir . '/tests/e2e/smoke.spec.ts', "export {};\n");
		$this->assertTrue(TestE2eCommand::directoryHasSpecFiles($this->tempDir . '/tests/e2e'));
	}
}
