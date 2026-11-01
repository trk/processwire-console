<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Traits\InteractWithProcessWire;
use function Laravel\Prompts\note;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

final class UserListCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('user:list')
			->setDescription('List users in the ProcessWire installation.')
			->addOption('search', null, InputOption::VALUE_OPTIONAL, 'Search by name contains')
			->addOption('role', null, InputOption::VALUE_OPTIONAL, 'Filter by role name')
			->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Number of users to show', '50')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$search = $input->getOption('search') ? (string) $input->getOption('search') : '';
		$role = $input->getOption('role') ? (string) $input->getOption('role') : '';
		$limit = max(1, (int) $input->getOption('limit'));
		$asJson = (bool) $input->getOption('json');

		$sanitizer = \ProcessWire\wire('sanitizer');

		$selector = [];
		if ($search !== '') {
			$safeSearch = $sanitizer->selectorValue($search);
			$selector[] = "name%={$safeSearch}";
		}
		if ($role !== '') {
			$safeRole = $sanitizer->selectorValue($role);
			$selector[] = "roles={$safeRole}";
		}
		$selector[] = "limit={$limit}";

		$users = \ProcessWire\wire('users')->find(implode(', ', $selector));
		if (!$users->count()) {
			if ($asJson) {
				return $this->outputJsonSuccess($output, ['items' => [], 'limit' => $limit]);
			}
			warning('No users found.');
			return Command::SUCCESS;
		}

		if ($asJson) {
			$items = [];
			foreach ($users as $user) {
				$roles = [];
				foreach ($user->roles as $r) {
					$roles[] = $r->name;
				}
				$flags = [];
				if ($user->isSuperuser()) {
					$flags[] = 'superuser';
				}
				if ($user->isUnpublished()) {
					$flags[] = 'unpub';
				}
				$items[] = [
					'id' => $user->id,
					'name' => $user->name,
					'email' => (string) ($user->email ?? ''),
					'roles' => $roles,
					'flags' => $flags ?: ['ok'],
				];
			}
			return $this->outputJsonSuccess($output, ['items' => $items, 'limit' => $limit]);
		}

		$headers = ['ID', 'Name', 'Email', 'Roles', 'Flags'];
		$rows = [];
		foreach ($users as $user) {
			$roles = [];
			foreach ($user->roles as $r) {
				$roles[] = $r->name;
			}
			$flags = [];
			if ($user->isSuperuser()) {
				$flags[] = 'superuser';
			}
			if ($user->isUnpublished()) {
				$flags[] = 'unpub';
			}
			$rows[] = [
				$user->id,
				$user->name,
				(string) ($user->email ?? ''),
				implode(',', $roles),
				implode('|', $flags) ?: 'ok',
			];
		}

		table($headers, $rows);
		note("Showing up to {$limit} user(s).");
		return Command::SUCCESS;
	}
}
