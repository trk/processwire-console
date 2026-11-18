<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Traits\InteractWithProcessWire;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;

final class PermissionDeleteCommand extends Command
{
	use InteractWithProcessWire;

	/**
	 * Built-in core permissions that must not be deleted.
	 *
	 * @var list<string>
	 */
	private const SYSTEM_PERMISSIONS = [
		'page-view',
		'page-edit',
		'page-add',
		'page-create',
		'page-delete',
		'page-move',
		'page-sort',
		'page-template',
		'page-lock',
		'page-hide',
		'page-publish',
		'page-edit-created',
		'page-edit-trash-created',
		'page-edit-images',
		'page-edit-recent',
		'page-clone',
		'page-clone-tree',
		'page-lister',
		'profile-edit',
		'user-admin',
		'user-admin-all',
		'logs-view',
		'logs-edit',
		'lang-edit',
	];

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
		$permName = $input->getOption('name') ? trim((string) $input->getOption('name')) : '';
		$dryRun = (bool) $input->getOption('dry-run');
		$asJson = (bool) $input->getOption('json');
		$force = (bool) $input->getOption('force');

		if ($permName === '' && $this->canPrompt($input, $asJson, $force)) {
			$permName = $this->searchPermission('Select a permission to delete');
			if ($permName === '') {
				return Command::SUCCESS;
			}
		}

		if ($permName === '') {
			return $this->failCommand($output, $asJson, 'MISSING_NAME', 'Provide --name.');
		}

		$permission = $this->findNamedSystemPage('permissions', $permName);
		if (!($permission instanceof \ProcessWire\Permission) || !$permission->id) {
			return $this->failCommand($output, $asJson, 'NOT_FOUND', "Permission not found: {$permName}");
		}

		if ($this->isProtectedSystemPage($permission) || in_array((string) $permission->name, self::SYSTEM_PERMISSIONS, true)) {
			return $this->failCommand($output, $asJson, 'SYSTEM_PERMISSION_PROTECTED', "Refusing to delete core permission '{$permission->name}'.");
		}

		if (!$force && !$dryRun) {
			if ($asJson || !$input->isInteractive()) {
				return $this->failCommand($output, $asJson, 'CONFIRMATION_REQUIRED', 'Use --force to run permission:delete non-interactively.');
			}
			if (!confirm("Delete permission '{$permission->name}'?", false)) {
				note('Aborted.');
				return Command::SUCCESS;
			}
		}

		$result = ['name' => (string) $permission->name, 'dryRun' => $dryRun];
		if ($dryRun) {
			if ($asJson) {
				return $this->outputJsonSuccess($output, $result);
			}
			note("Dry-run: would delete permission '{$permission->name}'.");
			return Command::SUCCESS;
		}

		try {
			\ProcessWire\wire('permissions')->delete($permission);
		} catch (\Throwable $e) {
			return $this->failCommand($output, $asJson, 'DELETE_FAILED', "Delete failed for permission '{$permission->name}': " . $e->getMessage());
		}

		if ($asJson) {
			return $this->outputJsonSuccess($output, $result + ['deleted' => true]);
		}

		info("Deleted permission '{$permission->name}'.");
		return Command::SUCCESS;
	}
}
