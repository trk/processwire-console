<?php

declare(strict_types=1);

namespace Totoglu\Console\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Totoglu\Console\Database\Seeder;
use Totoglu\Console\Support\FeatureDiscoverer;

final class FeatureDiscovererTest extends TestCase
{
	private string $tempDir;

	protected function setUp(): void
	{
		parent::setUp();
		$this->tempDir = sys_get_temp_dir() . '/pw_console_test_' . uniqid('', true);
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

	public function testExtractNamespaceStandardAndBracketed(): void
	{
		$standardFile = $this->tempDir . '/StandardNs.php';
		file_put_contents($standardFile, "<?php\n\ndeclare(strict_types=1);\n\nnamespace App\\Custom\\Commands;\n\nclass StandardNs {}\n");

		$bracketedFile = $this->tempDir . '/BracketedNs.php';
		file_put_contents($bracketedFile, "<?php\nnamespace Vendor\\Module\\Queue {\n    class BracketedNs {}\n}\n");

		$globalFile = $this->tempDir . '/GlobalNs.php';
		file_put_contents($globalFile, "<?php\nclass GlobalNs {}\n");

		$this->assertSame('App\\Custom\\Commands', FeatureDiscoverer::extractNamespace($standardFile));
		$this->assertSame('Vendor\\Module\\Queue', FeatureDiscoverer::extractNamespace($bracketedFile));
		$this->assertNull(FeatureDiscoverer::extractNamespace($globalFile));
		$this->assertNull(FeatureDiscoverer::extractNamespace($this->tempDir . '/NonExistent.php'));
	}

	public function testResolveClassFromFileWithSubclassCheck(): void
	{
		$seederClass = 'TestCustomSeeder_' . substr(md5($this->tempDir), 0, 8);
		$seederFile = $this->tempDir . '/' . $seederClass . '.php';
		file_put_contents(
			$seederFile,
			"<?php\ndeclare(strict_types=1);\nnamespace Site\\Seeders;\nuse Totoglu\\Console\\Database\\Seeder;\nfinal class {$seederClass} extends Seeder {\n    public function run(): void {}\n}\n"
		);

		$resolved = FeatureDiscoverer::resolveClassFromFile($seederFile, $seederClass, Seeder::class);
		$this->assertSame('Site\\Seeders\\' . $seederClass, $resolved);

		// Expect null if required subclass does not match
		$wrongSubclass = FeatureDiscoverer::resolveClassFromFile($seederFile, $seederClass, Command::class);
		$this->assertNull($wrongSubclass);

		// Expect null for abstract subclasses so callers never instantiate abstract classes
		$abstractClass = 'AbstractSeeder_' . substr(md5($this->tempDir), 0, 8);
		$abstractFile = $this->tempDir . '/' . $abstractClass . '.php';
		file_put_contents(
			$abstractFile,
			"<?php\ndeclare(strict_types=1);\nnamespace Site\\Seeders;\nuse Totoglu\\Console\\Database\\Seeder;\nabstract class {$abstractClass} extends Seeder {}\n"
		);
		$this->assertNull(FeatureDiscoverer::resolveClassFromFile($abstractFile, $abstractClass, Seeder::class));

		// Reject path traversal identifiers in classBasename
		$this->assertNull(FeatureDiscoverer::resolveClassFromFile($seederFile, '../../EvilClass', Seeder::class));
	}

	public function testDiscoverComposerCommandsFromInstalledJsonAndPathRepos(): void
	{
		$cmdClass1 = 'FakePkgCommand1_' . substr(md5($this->tempDir), 0, 8);
		$cmdClass2 = 'FakePkgCommand2_' . substr(md5($this->tempDir), 0, 8);

		eval("namespace Vendor\\FakePkg; class {$cmdClass1} extends \\Symfony\\Component\\Console\\Command\\Command { protected function configure(): void { \$this->setName('fake:one'); } }");
		eval("namespace LocalRepo\\Pkg; class {$cmdClass2} extends \\Symfony\\Component\\Console\\Command\\Command { protected function configure(): void { \$this->setName('fake:two'); } }");

		$fqcn1 = "Vendor\\FakePkg\\{$cmdClass1}";
		$fqcn2 = "LocalRepo\\Pkg\\{$cmdClass2}";

		mkdir($this->tempDir . '/vendor/composer', 0777, true);
		file_put_contents($this->tempDir . '/vendor/composer/installed.json', json_encode([
			'packages' => [
				[
					'name' => 'vendor/fake-pkg',
					'extra' => [
						'processwire-console' => [
							'commands' => [$fqcn1, 'NonExistent\\CommandClass'],
						],
					],
				],
			],
		]));

		mkdir($this->tempDir . '/packages/local-pkg', 0777, true);
		file_put_contents($this->tempDir . '/packages/local-pkg/composer.json', json_encode([
			'name' => 'local/pkg',
			'extra' => [
				'processwire-console' => [
					'commands' => [$fqcn2],
				],
			],
		]));

		file_put_contents($this->tempDir . '/composer.json', json_encode([
			'repositories' => [
				[
					'type' => 'path',
					'url' => 'packages/*',
				],
			],
		]));

		$commands = FeatureDiscoverer::discoverComposerCommands($this->tempDir);
		$this->assertSame([$fqcn1, $fqcn2], $commands);
	}

	public function testResolveStorageTableOptionsValidatesEngineAndCharset(): void
	{
		$validConfig = (object) ['dbEngine' => 'MyISAM', 'dbCharset' => 'utf8'];
		$this->assertSame(['engine' => 'MyISAM', 'charset' => 'utf8'], FeatureDiscoverer::resolveStorageTableOptions($validConfig));

		$invalidConfig = (object) ['dbEngine' => 'DROP TABLE x;', 'dbCharset' => 'utf8mb4; DROP TABLE y'];
		$this->assertSame(['engine' => 'InnoDB', 'charset' => 'utf8mb4'], FeatureDiscoverer::resolveStorageTableOptions($invalidConfig));
	}

	public function testReadLastLinesReadsReverseChunksAccurately(): void
	{
		$logFile = $this->tempDir . '/sample.txt';
		$lines = [];
		for ($i = 1; $i <= 50; $i++) {
			$lines[] = "Log entry #{$i}";
		}
		file_put_contents($logFile, implode("\n", $lines) . "\n");

		$lastFive = FeatureDiscoverer::readLastLines($logFile, 5);
		$this->assertSame([
			'Log entry #46',
			'Log entry #47',
			'Log entry #48',
			'Log entry #49',
			'Log entry #50',
		], $lastFive);

		$this->assertSame([], FeatureDiscoverer::readLastLines($logFile, 0));
		$this->assertSame([], FeatureDiscoverer::readLastLines($this->tempDir . '/missing.txt', 10));
	}

	public function testDiscoverPathsFromRootsHandlesSingleAndMultiSegmentFeaturesForInstalledModulesOnly(): void
	{
		$siteRoot = $this->tempDir . '/site';
		$modulesRoot = $siteRoot . '/modules';

		mkdir($siteRoot . '/tests/e2e', 0777, true);
		mkdir($modulesRoot . '/ProcessAnalytics/tests/e2e', 0777, true);
		mkdir($modulesRoot . '/Blog/tests/e2e', 0777, true);
		mkdir($modulesRoot . '/AdminTools/tests/e2e', 0777, true);
		file_put_contents($modulesRoot . '/AdminTools/ProcessAdminTools.module.php', "<?php\n");
		mkdir($modulesRoot . '/UninstalledModule/tests/e2e', 0777, true);

		$installed = [
			'ProcessAnalytics' => true,
			'Blog' => true,
			'ProcessAdminTools' => true,
		];
		$checker = static fn(string $moduleName): bool => isset($installed[$moduleName]);

		$singleSegment = FeatureDiscoverer::discoverPathsFromRoots($siteRoot, $modulesRoot, 'tests', $checker);
		$this->assertSame([
			$siteRoot . '/tests/',
			$modulesRoot . '/AdminTools/tests/',
			$modulesRoot . '/Blog/tests/',
			$modulesRoot . '/ProcessAnalytics/tests/',
		], $singleSegment);

		$nestedSegment = FeatureDiscoverer::discoverPathsFromRoots($siteRoot, $modulesRoot, 'tests/e2e', $checker);
		$this->assertSame([
			$siteRoot . '/tests/e2e/',
			$modulesRoot . '/AdminTools/tests/e2e/',
			$modulesRoot . '/Blog/tests/e2e/',
			$modulesRoot . '/ProcessAnalytics/tests/e2e/',
		], $nestedSegment);
	}

	public function testHasDatabaseConfigurationSupportsMysqlPgsqlAndSqlite(): void
	{
		$this->assertFalse(FeatureDiscoverer::hasDatabaseConfiguration(null));
		$this->assertFalse(FeatureDiscoverer::hasDatabaseConfiguration((object) []));
		$this->assertFalse(FeatureDiscoverer::hasDatabaseConfiguration((object) [
			'dbType' => 'mysql',
			'dbName' => '',
			'dbFile' => '',
		]));
		$this->assertFalse(FeatureDiscoverer::hasDatabaseConfiguration((object) [
			'dbType' => 'mysql',
			'dbName' => '   ',
			'dbFile' => '   ',
		]));

		$this->assertTrue(FeatureDiscoverer::hasDatabaseConfiguration((object) [
			'dbType' => 'mysql',
			'dbName' => 'processwire',
		]));
		$this->assertTrue(FeatureDiscoverer::hasDatabaseConfiguration((object) [
			'dbType' => 'pgsql',
			'dbName' => 'pw_pg',
		]));
		$this->assertTrue(FeatureDiscoverer::hasDatabaseConfiguration((object) [
			'dbType' => 'sqlite',
			'dbName' => '',
			'dbFile' => 'site.sqlite',
		]));
		$this->assertTrue(FeatureDiscoverer::hasDatabaseConfiguration((object) [
			'dbType' => 'sqlite',
			'dbName' => '',
			'dbFile' => '',
		]));
		$this->assertTrue(FeatureDiscoverer::hasDatabaseConfiguration((object) [
			'dbType' => 'SQLITE',
			'dbName' => '',
			'dbFile' => '',
		]));
		$this->assertTrue(FeatureDiscoverer::hasDatabaseConfiguration((object) [
			'dbFile' => '/tmp/test.sqlite',
		]));

		$makeMagicConfig = static function (array $props): object {
			return new class($props) {
				/** @var array<string, mixed> */
				private array $props;

				/** @param array<string, mixed> $props */
				public function __construct(array $props)
				{
					$this->props = $props;
				}

				public function __isset(string $name): bool
				{
					return isset($this->props[$name]) && $this->props[$name] !== '';
				}

				public function __get(string $name): mixed
				{
					return $this->props[$name] ?? null;
				}
			};
		};

		$this->assertTrue(FeatureDiscoverer::hasDatabaseConfiguration($makeMagicConfig([
			'dbType' => 'sqlite',
			'dbName' => '',
			'dbFile' => 'custom.sqlite',
		])));
		$this->assertTrue(FeatureDiscoverer::hasDatabaseConfiguration($makeMagicConfig([
			'dbType' => 'sqlite',
			'dbName' => '',
			'dbFile' => '',
		])));
		$this->assertFalse(FeatureDiscoverer::hasDatabaseConfiguration($makeMagicConfig([
			'dbType' => 'mysql',
			'dbName' => '',
			'dbFile' => '',
		])));

		$wireBinSource = (string) file_get_contents(dirname(__DIR__, 2) . '/bin/wire');
		$this->assertStringContainsString('FeatureDiscoverer::hasDatabaseConfiguration($config)', $wireBinSource);
		$this->assertStringNotContainsString('if ($config->dbName)', $wireBinSource);
	}
}
