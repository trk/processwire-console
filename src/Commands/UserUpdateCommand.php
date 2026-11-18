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
use function Laravel\Prompts\warning;

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
			->addOption('force', 'f', InputOption::VALUE_NONE, 'Confirm privileged superuser mutations')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write changes')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$rawId = $input->getOption('id');
		$idStr = ($rawId !== null && $rawId !== '') ? trim((string) $rawId) : '';
		$username = $input->getOption('name') ? trim((string) $input->getOption('name')) : '';
		$rawEmail = $input->getOption('email');
		$rawPassword = $input->getOption('password');
		$email = ($rawEmail !== null && $rawEmail !== '') ? trim((string) $rawEmail) : '';
		$password = ($rawPassword !== null && $rawPassword !== '') ? (string) $rawPassword : '';
		$addRoles = $input->getOption('add-role') ? array_values(array_map('strval', (array) $input->getOption('add-role'))) : [];
		$removeRoles = $input->getOption('remove-role') ? array_values(array_map('strval', (array) $input->getOption('remove-role'))) : [];
		$force = (bool) $input->getOption('force');
		$dryRun = (bool) $input->getOption('dry-run');
		$asJson = (bool) $input->getOption('json');

		if ($idStr === '' && $username === '' && $this->canPrompt($input, $asJson, $force)) {
			$username = $this->searchUser('Select a user to update');
			if ($username === '') {
				return Command::SUCCESS;
			}
		}

		if ($idStr === '' && $username === '') {
			return $this->failCommand($output, $asJson, 'MISSING_IDENTIFIER', 'Provide --id or --name.');
		}

		$users = \ProcessWire\wire('users');
		if ($idStr !== '') {
			if (!ctype_digit($idStr)) {
				return $this->failCommand($output, $asJson, 'INVALID_ID', 'Option --id must be a positive integer.');
			}
			$user = $users->get((int) $idStr);
		} else {
			$user = $this->findNamedSystemPage('users', $username);
		}

		if (!($user instanceof \ProcessWire\User) || !$user->id) {
			return $this->failCommand($output, $asJson, 'NOT_FOUND', 'User not found.');
		}

		if ((string) $user->name === 'guest' || $user->isGuest()) {
			return $this->failCommand($output, $asJson, 'GUEST_PROTECTED', 'Refusing to modify the built-in guest user.');
		}

		if ($email === '' && $password === '' && $addRoles === [] && $removeRoles === []) {
			return $this->failCommand($output, $asJson, 'MISSING_CHANGES', 'Provide at least one change (--email, --password, --add-role, or --remove-role).');
		}

		$normalizedRemove = array_map(static fn(string $r): string => strtolower(trim($r)), $removeRoles);
		if (in_array('guest', $normalizedRemove, true)) {
			return $this->failCommand($output, $asJson, 'SYSTEM_ROLE_PROTECTED', "Refusing to remove the mandatory 'guest' role from a user.");
		}

		$resolvedAddRoles = [];
		foreach ($addRoles as $r) {
			$roleObj = $this->findNamedSystemPage('roles', trim($r));
			if (!($roleObj instanceof \ProcessWire\Role) || !$roleObj->id) {
				return $this->failCommand($output, $asJson, 'ROLE_NOT_FOUND', "Role not found: '{$r}'.");
			}
			$resolvedAddRoles[] = $roleObj;
		}

		$resolvedRemoveRoles = [];
		foreach ($removeRoles as $r) {
			$roleObj = $this->findNamedSystemPage('roles', trim($r));
			if (!($roleObj instanceof \ProcessWire\Role) || !$roleObj->id) {
				return $this->failCommand($output, $asJson, 'ROLE_NOT_FOUND', "Role not found: '{$r}'.");
			}
			$resolvedRemoveRoles[] = $roleObj;
		}

		if (in_array('superuser', $normalizedRemove, true) && $user->isSuperuser()) {
			$superuserRole = $this->findNamedSystemPage('roles', 'superuser');
			if ($superuserRole !== null) {
				$superuserCount = (int) $users->count('roles=' . (int) $superuserRole->id . ', include=all');
				if ($superuserCount <= 1) {
					return $this->failCommand($output, $asJson, 'LAST_SUPERUSER_PROTECTED', 'Refusing to remove superuser role from the last superuser account.');
				}
			}
		}

		if ($email !== '') {
			$sanEmail = (string) \ProcessWire\wire('sanitizer')->email($email);
			if ($sanEmail === '' || filter_var($sanEmail, FILTER_VALIDATE_EMAIL) === false) {
				return $this->failCommand($output, $asJson, 'INVALID_EMAIL', 'Invalid email address.');
			}
			$email = $sanEmail;
		}

		$normalizedAdd = array_map(static fn(string $r): string => strtolower(trim($r)), $addRoles);
		$requiresSuperuserConfirm = $user->isSuperuser() || in_array('superuser', $normalizedAdd, true);

		if ($requiresSuperuserConfirm && !$force && !$dryRun) {
			if ($asJson || !$input->isInteractive()) {
				return $this->failCommand($output, $asJson, 'CONFIRMATION_REQUIRED', 'Modifying a superuser or granting the superuser role non-interactively requires --force.');
			}
			if (!confirm("Modify superuser privileges/account for '{$user->name}'?", default: false)) {
				note('Aborted.');
				return Command::SUCCESS;
			}
		}

		$result = [
			'id' => (int) $user->id,
			'name' => (string) $user->name,
			'email' => $email,
			'addRoles' => array_map(static fn(\ProcessWire\Role $r): string => (string) $r->name, $resolvedAddRoles),
			'removeRoles' => array_map(static fn(\ProcessWire\Role $r): string => (string) $r->name, $resolvedRemoveRoles),
			'dryRun' => $dryRun,
		];

		if ($dryRun) {
			if ($asJson) {
				return $this->outputJsonSuccess($output, $result);
			}
			note("Dry-run: would update user '{$user->name}'.");
			return Command::SUCCESS;
		}

		try {
			$user->of(false);

			if ($email !== '') {
				$user->email = $email;
			}
			if ($password !== '') {
				$user->pass = $password;
			}

			foreach ($resolvedAddRoles as $role) {
				if (!$user->hasRole($role)) {
					$user->addRole($role);
				}
			}
			foreach ($resolvedRemoveRoles as $role) {
				if ($user->hasRole($role)) {
					$user->removeRole($role);
				}
			}

			$user->save();
		} catch (\Throwable $e) {
			return $this->failCommand($output, $asJson, 'SAVE_FAILED', $e->getMessage());
		}

		if ($asJson) {
			return $this->outputJsonSuccess($output, $result + ['saved' => true]);
		}

		info("Updated user '{$user->name}'.");
		return Command::SUCCESS;
	}
}
