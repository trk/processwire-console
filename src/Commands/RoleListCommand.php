<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Traits\InteractWithProcessWire;
use function Laravel\Prompts\table;

final class RoleListCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('role:list')
			->setDescription('List all roles in ProcessWire.')
			->addOption('json', null, \Symfony\Component\Console\Input\InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$roles = \ProcessWire\wire('roles');

		$asJson = (bool) $input->getOption('json');
		$items = [];
		foreach ($roles as $role) {
			$permissions = [];
			foreach ($role->permissions as $p) {
				$permissions[] = $p->name;
			}
			$items[] = ['id' => $role->id, 'name' => $role->name, 'permissions' => $permissions];
		}

		if ($asJson) {
			return $this->outputJsonSuccess($output, ['items' => $items, 'total' => count($items)]);
		}

		$tableData = array_map(static fn(array $r): array => [$r['id'], $r['name'], implode(', ', $r['permissions'])], $items);
		table(['ID', 'Name', 'Permissions'], $tableData);

		return Command::SUCCESS;
	}
}
