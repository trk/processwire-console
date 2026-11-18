<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Traits\InteractWithProcessWire;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\note;
use function Laravel\Prompts\password;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

final class UserCreateCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('user:create')
			->setDescription('Create a new ProcessWire user.')
			->addArgument('username', InputArgument::OPTIONAL, 'The username')
			->addArgument('email', InputArgument::OPTIONAL, 'The email address')
			->addOption('name', null, InputOption::VALUE_OPTIONAL, 'The username')
			->addOption('email', null, InputOption::VALUE_OPTIONAL, 'The email address')
			->addOption('role', 'r', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Roles to assign', ['guest'])
			->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'Password (if omitted, one will be generated)')
			->addOption('interactive', 'i', InputOption::VALUE_NONE, 'Prompt for username/email/password/roles when missing')
			->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip interactive prompts')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$username = $input->getOption('name') ?: $input->getArgument('username');
		$email = $input->getOption('email') ?: $input->getArgument('email');
		$roles = (array) $input->getOption('role');
		$passOpt = $input->getOption('password');
		$wantInteractive = (bool) $input->getOption('interactive');
		$force = (bool) $input->getOption('force');
		$asJson = (bool) $input->getOption('json');

		if (($wantInteractive || !$username || !$email) && $this->canPrompt($input, $asJson, $force)) {
			if (!$username) {
				$username = text('Username', required: true);
			}
			if (!$email) {
				$email = text(
					'Email',
					required: true,
					validate: function (string $value): ?string {
						$v = \ProcessWire\wire('sanitizer')->email($value);
						return ($v !== '' && filter_var($v, FILTER_VALIDATE_EMAIL) !== false) ? null : 'Invalid email format';
					}
				);
			}
			$allRoles = [];
			foreach (\ProcessWire\wire('roles') as $r) {
				$allRoles[] = (string) $r->name;
			}
			$defaultRoles = $roles !== [] ? array_values(array_map('strval', $roles)) : ['guest'];
			$sel = multiselect('Assign roles', $allRoles, default: $defaultRoles);
			$roles = $sel !== [] ? array_values(array_map('strval', $sel)) : ['guest'];
			if (!$passOpt) {
				$p1 = password('Password (leave empty to auto-generate)', required: false);
				if ($p1 !== '') {
					$p2 = password('Confirm password', required: true);
					$attempts = 1;
					while ($p1 !== $p2 && $attempts < 3) {
						error('Passwords do not match.');
						$p1 = password('Password', required: true);
						$p2 = password('Confirm password', required: true);
						$attempts++;
					}
					if ($p1 !== $p2) {
						return $this->failCommand($output, $asJson, 'PASSWORD_MISMATCH', 'Password confirmation failed.');
					}
					$passOpt = $p1;
				}
			}
		}

		if (!$username || !$email) {
			return $this->failCommand($output, $asJson, 'MISSING_OPTIONS', 'Provide username and email (via --name/--email or arguments).');
		}

		$san = \ProcessWire\wire('sanitizer');
		$rawUsername = trim((string) $username);
		$cleanUsername = (string) $san->pageName($rawUsername, true);
		if ($cleanUsername === '' || $cleanUsername !== strtolower($rawUsername) || ctype_digit($cleanUsername)) {
			return $this->failCommand($output, $asJson, 'INVALID_NAME', "Invalid username '{$rawUsername}'. Must be a valid non-numeric ProcessWire page name.");
		}

		$emailSan = (string) $san->email((string) $email);
		if ($emailSan === '' || filter_var($emailSan, FILTER_VALIDATE_EMAIL) === false) {
			return $this->failCommand($output, $asJson, 'INVALID_EMAIL', 'Invalid email address.');
		}
		$email = $emailSan;
		$password = is_string($passOpt) && $passOpt !== '' ? $passOpt : bin2hex(random_bytes(8));

		$existing = $this->findNamedSystemPage('users', $cleanUsername);
		if ($existing !== null) {
			return $this->failCommand($output, $asJson, 'ALREADY_EXISTS', "User '{$cleanUsername}' already exists.");
		}

		$resolvedRoles = [];
		foreach ($roles as $roleName) {
			$roleNameStr = trim((string) $roleName);
			if ($roleNameStr === '') {
				continue;
			}
			$role = $this->findNamedSystemPage('roles', $roleNameStr);
			if (!($role instanceof \ProcessWire\Role) || !$role->id) {
				return $this->failCommand($output, $asJson, 'ROLE_NOT_FOUND', "Role not found: '{$roleNameStr}'.");
			}
			$resolvedRoles[] = $role;
		}

		$normalizedRoles = array_values(array_map(static fn(\ProcessWire\Role $r): string => strtolower((string) $r->name), $resolvedRoles));
		if (in_array('superuser', $normalizedRoles, true) && !$force) {
			if ($asJson || !$input->isInteractive()) {
				return $this->failCommand($output, $asJson, 'CONFIRMATION_REQUIRED', 'Assigning the superuser role non-interactively requires --force.');
			}
			if (!confirm("Grant 'superuser' privileges to new user '{$cleanUsername}'?", default: false)) {
				note('Aborted.');
				return Command::SUCCESS;
			}
		}

		try {
			$u = \ProcessWire\wire('users')->add($cleanUsername);
			$u->of(false);
			$u->email = $email;
			$u->pass = $password;

			$assignedRoles = [];
			foreach ($resolvedRoles as $role) {
				$u->addRole($role);
				$assignedRoles[] = (string) $role->name;
			}

			$u->save();
		} catch (\Throwable $e) {
			return $this->failCommand($output, $asJson, 'SAVE_FAILED', $e->getMessage());
		}

		if ($asJson) {
			return $this->outputJsonSuccess($output, [
				'id' => (int) $u->id,
				'name' => (string) $u->name,
				'email' => $email,
				'roles' => $assignedRoles,
			]);
		}

		info("User '{$u->name}' created successfully.");
		table(['Username', 'Email', 'Password', 'Roles'], [
			[(string) $u->name, $email, $password, implode(', ', $assignedRoles)],
		]);

		return Command::SUCCESS;
	}
}
