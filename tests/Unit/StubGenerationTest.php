<?php

declare(strict_types=1);

namespace Totoglu\Console\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class StubGenerationTest extends TestCase
{
	private string $stubsDir;
	private string $tempDir;

	protected function setUp(): void
	{
		parent::setUp();
		$this->stubsDir = dirname(__DIR__, 2) . '/resources/stubs';
		$this->tempDir = sys_get_temp_dir() . '/pw_stub_test_' . uniqid('', true);
		mkdir($this->tempDir, 0777, true);
	}

	protected function tearDown(): void
	{
		if (is_dir($this->tempDir)) {
			foreach (glob($this->tempDir . '/*') ?: [] as $file) {
				@unlink($file);
			}
			@rmdir($this->tempDir);
		}
		parent::tearDown();
	}

	private function assertValidPhpSyntax(string $code, string $label): void
	{
		$file = $this->tempDir . '/' . preg_replace('/[^A-Za-z0-9_]/', '_', $label) . '.php';
		file_put_contents($file, $code);

		$output = [];
		$exitCode = 0;
		exec('php -l ' . escapeshellarg($file) . ' 2>&1', $output, $exitCode);

		$this->assertSame(0, $exitCode, "PHP syntax error in generated stub [{$label}]:\n" . implode("\n", $output));
	}

	public function testModuleStubsProduceValidPhpAndStrictTypes(): void
	{
		$moduleStubs = [
			'module.stub' => [
				'className' => 'CustomHelper',
				'baseName' => 'CustomHelper',
				'title' => addslashes("Iskender's Helper"),
				'summary' => addslashes("A summary with 'quotes'"),
				'autoload' => 'true',
				'singular' => 'true',
				'icon' => 'cog',
			],
			'fieldtype.stub' => [
				'className' => 'FieldtypeColor',
				'baseName' => 'Color',
				'title' => addslashes("Color Picker"),
				'summary' => addslashes("Stores a color's hex value"),
				'icon' => 'paint-brush',
			],
			'inputfield.stub' => [
				'className' => 'InputfieldColor',
				'baseName' => 'Color',
				'title' => addslashes("Color Input"),
				'summary' => addslashes("Renders a color's input"),
				'icon' => 'paint-brush',
			],
			'process.stub' => [
				'className' => 'ProcessDashboard',
				'baseName' => 'Dashboard',
				'title' => addslashes("Admin's Dashboard"),
				'summary' => addslashes("Process module for dashboard"),
				'icon' => 'dashboard',
				'pageName' => 'dashboard',
				'pageTitle' => addslashes("Admin's Dashboard"),
				'permissionName' => 'dashboard-view',
				'permissionTitle' => addslashes("View Admin's Dashboard"),
			],
		];

		foreach ($moduleStubs as $stubFile => $vars) {
			$path = $this->stubsDir . '/' . $stubFile;
			$this->assertFileExists($path);

			$content = (string) file_get_contents($path);
			$this->assertStringContainsString('declare(strict_types=1);', $content, "{$stubFile} must declare strict_types=1");

			foreach ($vars as $key => $val) {
				$content = str_replace('{{' . $key . '}}', $val, $content);
			}

			$this->assertValidPhpSyntax($content, $stubFile);

			if ($stubFile === 'fieldtype.stub') {
				$this->assertStringContainsString("get('InputfieldColor')", $content);
				$this->assertStringNotContainsString('InputfieldFieldtype', $content);
			}

			if ($stubFile === 'inputfield.stub') {
				$this->assertStringContainsString('htmlspecialchars((string) $this->attr(\'value\'), ENT_QUOTES, \'UTF-8\')', $content);
				$this->assertStringContainsString('$input[$name]', $content);
			}
		}
	}

	public function testMigrationQueueSeederTaskAndTestStubsProduceValidPhp(): void
	{
		$stubs = [
			'migration.stub',
			'migration.create-field.stub',
			'migration.create-template.stub',
			'migration.attach-field.stub',
			'migration.create-page.stub',
			'migration.create-role.stub',
			'migration.install-module.stub',
			'queue.stub',
			'seeder.stub',
			'task.stub',
			'pest.stub',
		];

		$replacements = [
			'{{namespace}}' => 'Site\\Generated',
			'{{class}}' => 'SampleGeneratedClass',
			'{{className}}' => 'SampleGeneratedClass',
			'{{fieldName}}' => 'featured_image',
			'{{fieldType}}' => 'FieldtypeImage',
			'{{templateName}}' => 'blog-post',
			'{{parentPath}}' => '/',
			'{{pageName}}' => 'sample-page',
			'{{pageTitle}}' => addslashes("Sample's Page"),
			'{{roleName}}' => 'editor',
			'{{moduleName}}' => 'ProcessRedirects',
			'{{description}}' => addslashes("Run daily sync for O'Reilly feed"),
		];

		foreach ($stubs as $stubFile) {
			$path = $this->stubsDir . '/' . $stubFile;
			$this->assertFileExists($path);

			$content = (string) file_get_contents($path);
			$this->assertStringContainsString('declare(strict_types=1);', $content, "{$stubFile} must declare strict_types=1");

			$rendered = str_replace(array_keys($replacements), array_values($replacements), $content);
			$this->assertValidPhpSyntax($rendered, $stubFile);
		}
	}
}
