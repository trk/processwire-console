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

final class UserUpdateCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('user:update')
			->setDescription('Update a user fields and roles.')
			->addOption('id', null, InputOption::VALUE_OPTIONAL, 'User ID')
			->addOption('name', null, InputOption::VALUE_OPTIONAL, 'User name')
			->addOption('email', null, InputOption::VALUE_REQUIRED, 'New email')
			->addOption('password', null, InputOption::VALUE_REQUIRED, 'New password (plain text)')
			->addOption('add-role', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Role(s) to add')
			->addOption('remove-role', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Role(s) to remove')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write changes')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$id = $input->getOption('id');
		$username = $input->getOption('name') ? (string) $input->getOption('name') : '';
		$email = $input->getOption('email') ? (string) $input->getOption('email') : '';
		$password = $input->getOption('password') ? (string) $input->getOption('password') : '';
		$addRoles = $input->getOption('add-role') ? (array) $input->getOption('add-role') : [];
		$removeRoles = $input->getOption('remove-role') ? (array) $input->getOption('remove-role') : [];
		$dryRun = (bool) $input->getOption('dry-run');
		$asJson = (bool) $input->getOption('json');

		if (!$id && !$username && !$asJson) {
			$username = $this->searchUser('Select a user to update');
			if ($username === '') {
				return Command::SUCCESS;
			}
		}

		if (!$id && !$username) {
			$msg = 'Provide --id or --name.';
			return $asJson ? $this->outputJsonError($output, 'MISSING_IDENTIFIER', $msg) : (error($msg) ?? Command::FAILURE);
		}

		$users = \ProcessWire\wire('users');
		$roles = \ProcessWire\wire('roles');
		$user = $id ? $users->get((int) $id) : $users->get((string) $username);
		if (!$user || !$user->id) {
			$msg = 'User not found.';
			return $asJson ? $this->outputJsonError($output, 'NOT_FOUND', $msg) : (error($msg) ?? Command::FAILURE);
		}

		$result = [
			'id' => $user->id,
			'name' => $user->name,
			'email' => $email,
			'addRoles' => $addRoles,
			'removeRoles' => $removeRoles,
			'dryRun' => $dryRun,
		];

		if ($dryRun) {
			if ($asJson) {
				return $this->outputJsonSuccess($output, $result);
			}
			note("Dry-run: would update user '{$user->name}'.");
			return Command::SUCCESS;
		}

		if ($email) {
			$user->email = $email;
		}
		if ($password) {
			$user->pass = $password;
		}

		foreach ($addRoles as $r) {
			$role = $roles->get((string) $r);
			if ($role && $role->id && !$user->hasRole($role)) {
				$user->addRole($role);
			}
		}
		foreach ($removeRoles as $r) {
			$role = $roles->get((string) $r);
			if ($role && $role->id && $user->hasRole($role)) {
				$user->removeRole($role);
			}
		}

		$user->save();

		if ($asJson) {
			return $this->outputJsonSuccess($output, $result + ['saved' => true]);
		}

		info("Updated user '{$user->name}'.");
		return Command::SUCCESS;
	}
}
