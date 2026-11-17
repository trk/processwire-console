<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Traits\InteractWithProcessWire;
use function Laravel\Prompts\table;

final class PermissionListCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('permission:list')
			->setDescription('List all permissions in ProcessWire.')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$permissions = \ProcessWire\wire('permissions');
		$asJson = (bool) $input->getOption('json');
		$items = [];
		foreach ($permissions as $p) {
			$items[] = [
				'id' => (int) $p->id,
				'name' => (string) $p->name,
				'title' => (string) ($p->title !== '' && $p->title !== null ? $p->title : ''),
			];
		}
		if ($asJson) {
			return $this->outputJsonSuccess($output, ['items' => $items, 'total' => count($items)]);
		}

		table(
			['ID', 'Name', 'Title'],
			array_map(static fn(array $r): array => [(string) $r['id'], $r['name'], $r['title'] !== '' ? $r['title'] : '-'], $items)
		);

		return Command::SUCCESS;
	}
}
