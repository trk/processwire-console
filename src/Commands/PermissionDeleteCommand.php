<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Traits\InteractWithProcessWire;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;

final class PermissionDeleteCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('permission:delete')
			->setDescription('Delete a custom permission.')
			->addOption('name', null, InputOption::VALUE_OPTIONAL, 'Permission name')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write changes')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output')
			->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip interactive confirmations');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$permName = $input->getOption('name') ? (string) $input->getOption('name') : '';
		$dryRun = (bool) $input->getOption('dry-run');
		$asJson = (bool) $input->getOption('json');
		$force = (bool) $input->getOption('force');

		if (!$permName && !$asJson) {
			$permName = $this->searchPermission('Select a permission to delete');
			if ($permName === '') {
				return Command::SUCCESS;
			}
		}

		if (!$permName) {
			$msg = 'Provide --name.';
			return $asJson ? $this->outputJsonError($output, 'MISSING_NAME', $msg) : (error($msg) ?? Command::FAILURE);
		}

		$permissions = \ProcessWire\wire('permissions');
		$permission = $permissions->get($permName);
		if (!$permission || !$permission->id) {
			$msg = "Permission not found: {$permName}";
			return $asJson ? $this->outputJsonError($output, 'NOT_FOUND', $msg) : (error($msg) ?? Command::FAILURE);
		}

		if (!$force && !$dryRun) {
			if ($asJson) {
				return $this->outputJsonError($output, 'CONFIRMATION_REQUIRED', 'Use --force to run permission:delete non-interactively with --json.');
			}
			if (!confirm("Delete permission '{$permName}'?", false)) {
				note('Aborted.');
				return Command::SUCCESS;
			}
		}

		$result = ['name' => $permName, 'dryRun' => $dryRun];
		if ($dryRun) {
			if ($asJson) {
				return $this->outputJsonSuccess($output, $result);
			}
			note("Dry-run: would delete permission '{$permName}'.");
			return Command::SUCCESS;
		}

		try {
			$permissions->delete($permission);
		} catch (\Throwable $e) {
			$msg = "Delete failed for permission '{$permName}': " . $e->getMessage();
			return $asJson ? $this->outputJsonError($output, 'DELETE_FAILED', $msg) : (error($msg) ?? Command::FAILURE);
		}

		if ($asJson) {
			return $this->outputJsonSuccess($output, $result + ['deleted' => true]);
		}

		info("Deleted permission '{$permName}'.");
		return Command::SUCCESS;
	}
}
