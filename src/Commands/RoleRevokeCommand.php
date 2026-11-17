<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Traits\InteractWithProcessWire;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;

final class RoleRevokeCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('role:revoke')
			->setDescription('Revoke a permission from a role.')
			->addOption('role', null, InputOption::VALUE_OPTIONAL, 'Role name')
			->addOption('permission', null, InputOption::VALUE_OPTIONAL, 'Permission name')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write changes')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$roleName = $input->getOption('role') ? trim((string) $input->getOption('role')) : '';
		$permName = $input->getOption('permission') ? trim((string) $input->getOption('permission')) : '';
		$dryRun = (bool) $input->getOption('dry-run');
		$asJson = (bool) $input->getOption('json');

		if ($roleName === '' && $this->canPrompt($input, $asJson)) {
			$roleName = $this->searchRole('Select a role');
			if ($roleName === '') {
				return Command::SUCCESS;
			}
		}

		if ($permName === '' && $roleName !== '' && $this->canPrompt($input, $asJson)) {
			$permName = $this->searchPermission("Select a permission to revoke from {$roleName}");
			if ($permName === '') {
				return Command::SUCCESS;
			}
		}

		if ($roleName === '' || $permName === '') {
			return $this->failCommand($output, $asJson, 'MISSING_OPTIONS', 'Provide --role and --permission.');
		}

		$role = $this->findNamedSystemPage('roles', $roleName);
		if (!($role instanceof \ProcessWire\Role) || !$role->id) {
			return $this->failCommand($output, $asJson, 'NOT_FOUND', "Role not found: {$roleName}");
		}

		if ((string) $role->name === 'superuser') {
			return $this->failCommand($output, $asJson, 'SYSTEM_ROLE_PROTECTED', "Refusing to modify permissions on the built-in 'superuser' role.");
		}

		$permission = $this->findNamedSystemPage('permissions', $permName);
		if (!($permission instanceof \ProcessWire\Permission) || !$permission->id) {
			return $this->failCommand($output, $asJson, 'NOT_FOUND', "Permission not found: {$permName}");
		}

		if ((string) $role->name === 'guest' && (string) $permission->name === 'page-view') {
			return $this->failCommand($output, $asJson, 'SYSTEM_ROLE_PROTECTED', "Refusing to revoke mandatory 'page-view' permission from the 'guest' role.");
		}

		$has = $role->hasPermission($permission);
		$result = ['role' => (string) $role->name, 'permission' => (string) $permission->name, 'has' => $has, 'dryRun' => $dryRun];
		if ($dryRun) {
			if ($asJson) {
				return $this->outputJsonSuccess($output, $result);
			}
			note("Dry-run: would revoke '{$permission->name}' from role '{$role->name}'.");
			return Command::SUCCESS;
		}

		if ($has) {
			$role->of(false);
			$role->removePermission($permission);
			$role->save();
		}

		if ($asJson) {
			return $this->outputJsonSuccess($output, $result + ['revoked' => true]);
		}

		info("Revoked '{$permission->name}' from role '{$role->name}'.");
		return Command::SUCCESS;
	}
}
