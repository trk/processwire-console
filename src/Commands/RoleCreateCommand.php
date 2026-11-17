<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Traits\InteractWithProcessWire;
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

		if (!$name && $this->canPrompt($input, $asJson)) {
			$name = text(
				label: 'Role name',
				required: true
			);
		}

		if (!$name) {
			return $this->failCommand($output, $asJson, 'MISSING_NAME', 'Provide role name via --name or argument.');
		}

		$rawName = trim((string) $name);
		$cleanName = (string) \ProcessWire\wire('sanitizer')->pageName($rawName, true);
		if ($cleanName === '' || $cleanName !== strtolower($rawName) || ctype_digit($cleanName)) {
			return $this->failCommand($output, $asJson, 'INVALID_NAME', "Invalid role name '{$rawName}'. Must be a valid non-numeric ProcessWire page name.");
		}

		$permsOpt = $input->getOption('permissions') ? (string) $input->getOption('permissions') : '';
		$wantInteractive = (bool) $input->getOption('interactive');

		$existing = $this->findNamedSystemPage('roles', $cleanName);
		if ($existing !== null) {
			return $this->failCommand($output, $asJson, 'ALREADY_EXISTS', "Role '{$cleanName}' already exists.");
		}

		$role = \ProcessWire\wire('roles')->add($cleanName);
		$role->of(false);
		if ($title) {
			$role->title = (string) $title;
		}
		$role->save();

		$granted = [];
		$permissionsApi = \ProcessWire\wire('permissions');
		if ($wantInteractive && $this->canPrompt($input, $asJson)) {
			$all = [];
			foreach ($permissionsApi as $p) {
				$all[] = (string) $p->name;
			}
			$choices = multiselect('Grant permissions to this role?', $all, required: false);
			if ($choices !== []) {
				foreach ($choices as $perm) {
					$p = $this->findNamedSystemPage('permissions', (string) $perm);
					if ($p instanceof \ProcessWire\Permission && $p->id && !$role->hasPermission($p)) {
						$role->addPermission($p);
						$granted[] = (string) $p->name;
					}
				}
				$role->save();
			}
		} elseif ($permsOpt !== '') {
			foreach (explode(',', $permsOpt) as $perm) {
				$perm = trim($perm);
				if ($perm === '') {
					continue;
				}
				$p = $this->findNamedSystemPage('permissions', $perm);
				if ($p instanceof \ProcessWire\Permission && $p->id && !$role->hasPermission($p)) {
					$role->addPermission($p);
					$granted[] = (string) $p->name;
				}
			}
			if ($granted !== []) {
				$role->save();
			}
		}

		if ($asJson) {
			return $this->outputJsonSuccess($output, [
				'id' => (int) $role->id,
				'name' => (string) $role->name,
				'title' => (string) ($role->title ?? ''),
				'permissions' => $granted,
			]);
		}

		$msg = "Role '{$role->name}' created.";
		if ($granted !== []) {
			$msg .= ' Granted: ' . implode(', ', $granted) . '.';
		}
		info($msg);

		return Command::SUCCESS;
	}
}
