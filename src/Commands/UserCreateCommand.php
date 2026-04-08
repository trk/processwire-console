<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use function Laravel\Prompts\text;
use function Laravel\Prompts\password;
use function Laravel\Prompts\multiselect;

final class UserCreateCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('user:create')
            ->setDescription('Create a new ProcessWire user.')
            ->addArgument('username', InputArgument::OPTIONAL, 'The username')
            ->addArgument('email', InputArgument::OPTIONAL, 'The email address')
            ->addOption('role', 'r', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Roles to assign', ['guest'])
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'Password (if omitted, one will be generated)')
            ->addOption('interactive', 'i', InputOption::VALUE_NONE, 'Prompt for username/email/password/roles when missing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $username = $input->getArgument('username');
        $email = $input->getArgument('email');
        $roles = $input->getOption('role');
        $passOpt = $input->getOption('password');
        $wantInteractive = (bool)$input->getOption('interactive');

        if (($wantInteractive || !$username || !$email) && $input->isInteractive()) {
            if (!$username) $username = text('Username', required: true);
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
            foreach (\ProcessWire\wire('roles') as $r) $allRoles[] = $r->name;
            $defaultRoles = is_array($roles) && $roles ? array_values($roles) : ['guest'];
            $sel = multiselect('Assign roles', $allRoles, default: $defaultRoles);
            $roles = $sel ?: ['guest'];
            if (!$passOpt) {
                $p1 = password('Password (leave empty to auto-generate)', required: false);
                if ($p1 !== '') {
                    $p2 = password('Confirm password', required: true);
                    $attempts = 1;
                    while ($p1 !== $p2 && $attempts < 3) {
                        $io->error('Passwords do not match.');
                        $p1 = password('Password', required: true);
                        $p2 = password('Confirm password', required: true);
                        $attempts++;
                    }
                    if ($p1 !== $p2) {
                        $io->error('Password confirmation failed.');
                        return Command::FAILURE;
                    }
                    $passOpt = $p1;
                }
            }
        }
        if (!$username || !$email) {
            $io->error("Provide <username> and <email> or run interactively to enter them.");
            return Command::FAILURE;
        }
        $san = \ProcessWire\wire('sanitizer');
        $emailSan = $san->email((string)$email);
        if (!$emailSan || !filter_var($emailSan, FILTER_VALIDATE_EMAIL)) {
            $io->error("Invalid email address.");
            return Command::FAILURE;
        }
        $email = $emailSan;
        $password = $passOpt ?: bin2hex(random_bytes(8));

        $existing = \ProcessWire\wire('users')->get($username);
        if ($existing && $existing->id) {
            $io->error("User '{$username}' already exists.");
            return Command::FAILURE;
        }

        $u = \ProcessWire\wire('users')->add($username);
        $u->email = $email;
        $u->pass = $password;

        foreach ($roles as $roleName) {
            $role = \ProcessWire\wire('roles')->get($roleName);
            if ($role->id) {
                $u->addRole($role);
            } else {
                $io->warning("Role '{$roleName}' not found, skipping.");
            }
        }

        $u->save();

        $io->success("User '{$username}' created successfully.");
        $io->table(['Username', 'Email', 'Password', 'Roles'], [
            [$username, $email, $password, implode(', ', $roles)]
        ]);

        return Command::SUCCESS;
    }
}
