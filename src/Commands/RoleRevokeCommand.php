<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Traits\InteractWithProcessWire;
use function Laravel\Prompts\error;
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
		$roleName = $input->getOption('role') ? (string) $input->getOption('role') : '';
		$permName = (string) $input->getOption('permission');
		$dryRun = (bool) $input->getOption('dry-run');
		$asJson = (bool) $input->getOption('json');

		if (!$roleName && !$asJson) {
			$roleName = $this->searchRole('Select a role');
			if ($roleName === '') {
				return Command::SUCCESS;
			}
		}

		if (!$permName && !$asJson && $roleName) {
			$permName = $this->searchPermission("Select a permission to revoke from {$roleName}");
			if ($permName === '') {
				return Command::SUCCESS;
			}
		}

		if (!$roleName || !$permName) {
			$msg = 'Provide --role and --permission.';
			return $asJson ? $this->outputJsonError($output, 'MISSING_OPTIONS', $msg) : (error($msg) ?? Command::FAILURE);
		}

		$roles = \ProcessWire\wire('roles');
		$permissions = \ProcessWire\wire('permissions');
		$role = $roles->get($roleName);
		$permission = $permissions->get($permName);
		if (!$role || !$role->id) {
			$msg = "Role not found: {$roleName}";
			return $asJson ? $this->outputJsonError($output, 'NOT_FOUND', $msg) : (error($msg) ?? Command::FAILURE);
		}
		if (!$permission || !$permission->id) {
			$msg = "Permission not found: {$permName}";
			return $asJson ? $this->outputJsonError($output, 'NOT_FOUND', $msg) : (error($msg) ?? Command::FAILURE);
		}

		$has = $role->hasPermission($permission);
		$result = ['role' => $roleName, 'permission' => $permName, 'has' => $has, 'dryRun' => $dryRun];
		if ($dryRun) {
			if ($asJson) {
				return $this->outputJsonSuccess($output, $result);
			}
			note("Dry-run: would revoke '{$permName}' from role '{$roleName}'.");
			return Command::SUCCESS;
		}

		if ($has) {
			$role->removePermission($permission);
			$role->save();
		}

		if ($asJson) {
			return $this->outputJsonSuccess($output, $result + ['revoked' => true]);
		}

		info("Revoked '{$permName}' from role '{$roleName}'.");
		return Command::SUCCESS;
	}
}
