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

		if (($wantInteractive || !$username || !$email) && $input->isInteractive() && !$force && !$asJson) {
			if (!$username) {
				$username = text('Username', required: true);
			}
			if (!$email) {
				$email = text(
					'Email',
					required: true,
					validate: function (string $value): ?string {
						$v = \ProcessWire\wire('sanitizer')->email($value);
						return ($v && filter_var($v, FILTER_VALIDATE_EMAIL)) ? null : 'Invalid email format';
					}
				);
			}
			$allRoles = [];
			foreach (\ProcessWire\wire('roles') as $r) {
				$allRoles[] = $r->name;
			}
			$defaultRoles = $roles ? array_values($roles) : ['guest'];
			$sel = multiselect('Assign roles', $allRoles, default: $defaultRoles);
			$roles = $sel ?: ['guest'];
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
						$msg = 'Password confirmation failed.';
						return $asJson ? $this->outputJsonError($output, 'PASSWORD_MISMATCH', $msg) : (error($msg) ?? Command::FAILURE);
					}
					$passOpt = $p1;
				}
			}
		}

		if (!$username || !$email) {
			$msg = 'Provide username and email (via --name/--email or arguments).';
			return $asJson ? $this->outputJsonError($output, 'MISSING_OPTIONS', $msg) : (error($msg) ?? Command::FAILURE);
		}

		$san = \ProcessWire\wire('sanitizer');
		$emailSan = $san->email((string) $email);
		if (!$emailSan || !filter_var($emailSan, FILTER_VALIDATE_EMAIL)) {
			$msg = 'Invalid email address.';
			return $asJson ? $this->outputJsonError($output, 'INVALID_EMAIL', $msg) : (error($msg) ?? Command::FAILURE);
		}
		$email = $emailSan;
		$password = $passOpt ?: bin2hex(random_bytes(8));

		$existing = \ProcessWire\wire('users')->get((string) $username);
		if ($existing && $existing->id) {
			$msg = "User '{$username}' already exists.";
			return $asJson ? $this->outputJsonError($output, 'ALREADY_EXISTS', $msg) : (error($msg) ?? Command::FAILURE);
		}

		$u = \ProcessWire\wire('users')->add((string) $username);
		$u->email = $email;
		$u->pass = $password;

		$assignedRoles = [];
		foreach ($roles as $roleName) {
			$role = \ProcessWire\wire('roles')->get((string) $roleName);
			if ($role && $role->id) {
				$u->addRole($role);
				$assignedRoles[] = (string) $roleName;
			} elseif (!$asJson) {
				warning("Role '{$roleName}' not found, skipping.");
			}
		}

		$u->save();

		if ($asJson) {
			return $this->outputJsonSuccess($output, [
				'id' => $u->id,
				'name' => $u->name,
				'email' => $email,
				'roles' => $assignedRoles,
			]);
		}

		info("User '{$username}' created successfully.");
		table(['Username', 'Email', 'Password', 'Roles'], [
			[$username, $email, $password, implode(', ', $assignedRoles)],
		]);

		return Command::SUCCESS;
	}
}
