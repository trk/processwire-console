<?php

declare(strict_types=1);

namespace Totoglu\Console\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Totoglu\Console\Commands\BackupListCommand;
use Totoglu\Console\Commands\BackupPurgeCommand;
use Totoglu\Console\Commands\CacheClearCommand;
use Totoglu\Console\Commands\DbBackupCommand;
use Totoglu\Console\Commands\DbSeedCommand;
use Totoglu\Console\Commands\FieldAttachCommand;
use Totoglu\Console\Commands\FieldDeleteCommand;
use Totoglu\Console\Commands\FieldInfoCommand;
use Totoglu\Console\Commands\FieldRenameCommand;
use Totoglu\Console\Commands\FieldUpdateCommand;
use Totoglu\Console\Commands\LogListCommand;
use Totoglu\Console\Commands\LogReadCommand;
use Totoglu\Console\Commands\LogTailCommand;
use Totoglu\Console\Commands\PagePublishCommand;
use Totoglu\Console\Commands\PageUnpublishCommand;
use Totoglu\Console\Commands\PageUpdateCommand;
use Totoglu\Console\Commands\PermissionCreateCommand;
use Totoglu\Console\Commands\RoleCreateCommand;
use Totoglu\Console\Commands\ScheduleRunCommand;
use Totoglu\Console\Commands\TemplateDeleteCommand;
use Totoglu\Console\Commands\TemplateFieldsReorderCommand;
use Totoglu\Console\Commands\TemplateInfoCommand;
use Totoglu\Console\Commands\TemplateRenameCommand;
use Totoglu\Console\Commands\TemplateUpdateCommand;
use Totoglu\Console\Commands\TinkerCommand;
use Totoglu\Console\Commands\UserCreateCommand;
use Totoglu\Console\Traits\InteractWithProcessWire;

final class CommandOptionsSpecTest extends TestCase
{
	public function testAllSeventyFourCommandsInstantiateCleanly(): void
	{
		$commandsDir = dirname(__DIR__, 2) . '/src/Commands';
		$files = glob($commandsDir . '/*Command.php') ?: [];

		$this->assertCount(74, $files, 'Expected exactly 74 command classes in src/Commands');

		foreach ($files as $file) {
			$class = 'Totoglu\\Console\\Commands\\' . basename($file, '.php');
			$this->assertTrue(class_exists($class), "Command class {$class} must exist");

			$instance = new $class();
			$this->assertInstanceOf(Command::class, $instance);
			$this->assertNotEmpty($instance->getName(), "{$class} must have a command name");
			$this->assertTrue(
				$instance->getDefinition()->hasOption('json'),
				"{$class} ({$instance->getName()}) must support the universal --json option"
			);
		}
	}

	public function testReadmeDocumentedOptionsExistOnCommands(): void
	{
		$spec = [
			PageUpdateCommand::class => ['id', 'path', 'title', 'name', 'parent', 'template', 'set', 'status', 'dry-run', 'json'],
			PagePublishCommand::class => ['id', 'path', 'dry-run', 'json'],
			PageUnpublishCommand::class => ['id', 'path', 'dry-run', 'json'],
			FieldInfoCommand::class => ['name', 'json'],
			FieldUpdateCommand::class => ['name', 'set', 'dry-run', 'json'],
			FieldRenameCommand::class => ['name', 'new-name', 'dry-run', 'json'],
			FieldAttachCommand::class => ['field', 'template', 'after', 'dry-run', 'json'],
			FieldDeleteCommand::class => ['name', 'force', 'dry-run', 'json'],
			TemplateInfoCommand::class => ['name', 'json'],
			TemplateUpdateCommand::class => ['name', 'set', 'dry-run', 'json'],
			TemplateRenameCommand::class => ['name', 'new-name', 'dry-run', 'json'],
			TemplateFieldsReorderCommand::class => ['template', 'fields', 'dry-run', 'json'],
			TemplateDeleteCommand::class => ['name', 'force', 'dry-run', 'json'],
			UserCreateCommand::class => ['name', 'email', 'password', 'role', 'interactive', 'force', 'json'],
			RoleCreateCommand::class => ['name', 'title', 'permissions', 'interactive', 'json'],
			PermissionCreateCommand::class => ['name', 'title', 'json'],
			CacheClearCommand::class => ['json'],
			LogListCommand::class => ['json'],
			LogReadCommand::class => ['from', 'to', 'limit', 'find', 'sort', 'json'],
			LogTailCommand::class => ['lines', 'follow', 'json'],
			DbBackupCommand::class => ['json'],
			BackupListCommand::class => ['json'],
			BackupPurgeCommand::class => ['keep', 'days', 'force', 'dry-run', 'json'],
			DbSeedCommand::class => ['class', 'force', 'json'],
			ScheduleRunCommand::class => ['json'],
			TinkerCommand::class => ['json'],
		];

		foreach ($spec as $commandClass => $expectedOptions) {
			/** @var Command $command */
			$command = new $commandClass();
			$definition = $command->getDefinition();

			foreach ($expectedOptions as $optName) {
				$this->assertTrue(
					$definition->hasOption($optName),
					sprintf('%s is missing documented option --%s', $commandClass, $optName)
				);
			}
		}

		// Verify repeatable --set (VALUE_IS_ARRAY) on PageUpdate, FieldUpdate, TemplateUpdate
		foreach ([PageUpdateCommand::class, FieldUpdateCommand::class, TemplateUpdateCommand::class] as $setCmdClass) {
			/** @var Command $cmd */
			$cmd = new $setCmdClass();
			$opt = $cmd->getDefinition()->getOption('set');
			$this->assertTrue($opt->isArray(), "{$setCmdClass} --set option must accept multiple values (VALUE_IS_ARRAY)");
		}
	}

	public function testTinkerCommandRespectsDisableTinkerEnvGuard(): void
	{
		$previousEnv = getenv('PW_CLI_DISABLE_TINKER');
		putenv('PW_CLI_DISABLE_TINKER=1');
		$_ENV['PW_CLI_DISABLE_TINKER'] = '1';

		try {
			$command = new TinkerCommand();
			$input = new ArrayInput(['--json' => true]);
			$output = new BufferedOutput();

			$exitCode = $command->run($input, $output);
			$this->assertSame(Command::FAILURE, $exitCode);

			$decoded = json_decode($output->fetch(), true);
			$this->assertIsArray($decoded);
			$this->assertFalse($decoded['ok']);
			$this->assertSame('TINKER_DISABLED', $decoded['error']['code']);
		} finally {
			if ($previousEnv === false) {
				putenv('PW_CLI_DISABLE_TINKER');
				unset($_ENV['PW_CLI_DISABLE_TINKER']);
			} else {
				putenv("PW_CLI_DISABLE_TINKER={$previousEnv}");
				$_ENV['PW_CLI_DISABLE_TINKER'] = $previousEnv;
			}
		}
	}

	public function testInteractWithProcessWireHelpers(): void
	{
		$helper = new class {
			use InteractWithProcessWire {
				parseKeyValueSets as public;
				outputJsonSuccess as public;
				outputJsonError as public;
			}
		};

		$parsed = $helper->parseKeyValueSets(['summary=Hello=World', 'count=42', 'invalid_entry']);
		$this->assertSame(['summary' => 'Hello=World', 'count' => '42'], $parsed);

		$out1 = new BufferedOutput();
		$helper->outputJsonSuccess($out1, ['id' => 123]);
		$this->assertSame(['ok' => true, 'data' => ['id' => 123]], json_decode($out1->fetch(), true));

		$out2 = new BufferedOutput();
		$helper->outputJsonError($out2, 'CUSTOM_ERR', 'Something failed');
		$this->assertSame(
			['ok' => false, 'error' => ['code' => 'CUSTOM_ERR', 'message' => 'Something failed']],
			json_decode($out2->fetch(), true)
		);
	}

	public function testAllDestructiveCommandsWithForceOptionEnforceConfirmationRequiredInJsonMode(): void
	{
		$destructiveCommands = [
			'BackupPurgeCommand.php',
			'DbRestoreCommand.php',
			'DbSeedCommand.php',
			'FieldDeleteCommand.php',
			'FieldDetachCommand.php',
			'LogClearAllCommand.php',
			'LogClearCommand.php',
			'MigrateFreshCommand.php',
			'MigrateRefreshCommand.php',
			'MigrateResetCommand.php',
			'MigrateRollbackCommand.php',
			'ModuleUninstallCommand.php',
			'PageTrashCommand.php',
			'PermissionDeleteCommand.php',
			'QueueClearCommand.php',
			'TemplateDeleteCommand.php',
			'UserDeleteCommand.php',
		];

		$commandsDir = dirname(__DIR__, 2) . '/src/Commands/';
		foreach ($destructiveCommands as $file) {
			$source = (string) file_get_contents($commandsDir . $file);
			$this->assertStringContainsString(
				'CONFIRMATION_REQUIRED',
				$source,
				"{$file} must return CONFIRMATION_REQUIRED when --json is passed without --force"
			);
			$this->assertStringNotContainsString(
				'if (!$force && !$asJson',
				$source,
				"{$file} must not bypass confirmation when --json is passed without --force"
			);
		}
	}
}
