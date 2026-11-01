<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Traits\InteractWithProcessWire;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\text;

final class RoleCreateCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('role:create')
			->setDescription('Create a new role.')
			->addArgument('name', InputArgument::OPTIONAL, 'The name of the role')
			->addOption('name', null, InputOption::VALUE_OPTIONAL, 'The name of the role')
			->addOption('title', null, InputOption::VALUE_OPTIONAL, 'The title of the role')
			->addOption('permissions', null, InputOption::VALUE_OPTIONAL, 'Comma-separated permission names to grant')
			->addOption('interactive', 'i', InputOption::VALUE_NONE, 'Prompt to select permissions to grant')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$asJson = (bool) $input->getOption('json');
		$name = $input->getOption('name') ?: $input->getArgument('name');
		$title = $input->getOption('title');

		if (!$name && $input->isInteractive() && !$asJson) {
			$name = text(
				label: 'Role name',
				required: true
			);
		}

		if (!$name) {
			$msg = 'Provide role name via --name or argument.';
			return $asJson ? $this->outputJsonError($output, 'MISSING_NAME', $msg) : (error($msg) ?? Command::FAILURE);
		}

		$permsOpt = $input->getOption('permissions') ? (string) $input->getOption('permissions') : '';
		$wantInteractive = (bool) $input->getOption('interactive');

		$existing = \ProcessWire\wire('roles')->get((string) $name);
		if ($existing && $existing->id) {
			$msg = "Role '{$name}' already exists.";
			return $asJson ? $this->outputJsonError($output, 'ALREADY_EXISTS', $msg) : (error($msg) ?? Command::FAILURE);
		}

		$role = \ProcessWire\wire('roles')->add((string) $name);
		if ($title) {
			$role->title = (string) $title;
		}
		$role->save();

		$granted = [];
		$permissionsApi = \ProcessWire\wire('permissions');
		if ($wantInteractive && $input->isInteractive() && !$asJson) {
			$all = [];
			foreach ($permissionsApi as $p) {
				$all[] = $p->name;
			}
			$choices = multiselect('Grant permissions to this role?', $all, required: false);
			if ($choices) {
				foreach ($choices as $perm) {
					$p = $permissionsApi->get((string) $perm);
					if ($p && $p->id && !$role->hasPermission($p)) {
						$role->addPermission($p);
						$granted[] = (string) $perm;
					}
				}
				$role->save();
			}
		} elseif ($permsOpt) {
			foreach (explode(',', $permsOpt) as $perm) {
				$perm = trim($perm);
				if ($perm === '') {
					continue;
				}
				$p = $permissionsApi->get($perm);
				if ($p && $p->id && !$role->hasPermission($p)) {
					$role->addPermission($p);
					$granted[] = $perm;
				}
			}
			if ($granted) {
				$role->save();
			}
		}

		if ($asJson) {
			return $this->outputJsonSuccess($output, [
				'id' => $role->id,
				'name' => $role->name,
				'title' => (string) ($role->title ?? ''),
				'permissions' => $granted,
			]);
		}

		$msg = "Role '{$name}' created.";
		if ($granted) {
			$msg .= ' Granted: ' . implode(', ', $granted) . '.';
		}
		info($msg);

		return Command::SUCCESS;
	}
}
