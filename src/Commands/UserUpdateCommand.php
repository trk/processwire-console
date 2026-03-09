<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class UserUpdateCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('user:update')
            ->setDescription('Update a user fields and roles.')
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'User ID')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'User name')
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'New email')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'New password (plain text)')
            ->addOption('add-role', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Role(s) to add')
            ->addOption('remove-role', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Role(s) to remove')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write changes')
            ->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $id = $input->getOption('id');
        $username = $input->getOption('name') ? (string)$input->getOption('name') : '';
        $email = $input->getOption('email') ? (string)$input->getOption('email') : '';
        $password = $input->getOption('password') ? (string)$input->getOption('password') : '';
        $addRoles = $input->getOption('add-role') ? (array)$input->getOption('add-role') : [];
        $removeRoles = $input->getOption('remove-role') ? (array)$input->getOption('remove-role') : [];
        $dryRun = (bool)$input->getOption('dry-run');
        $asJson = (bool)$input->getOption('json');

        if (!$id && !$username) {
            $io->error("Provide --id or --name.");
            return Command::FAILURE;
        }

        $users = \ProcessWire\wire('users');
        $roles = \ProcessWire\wire('roles');
        $user = $id ? $users->get((int)$id) : $users->get((string)$username);
        if (!$user || !$user->id) {
            $io->error("User not found.");
            return Command::FAILURE;
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
                $output->writeln(json_encode(['ok' => true, 'data' => $result], JSON_UNESCAPED_SLASHES));
            } else {
                $io->note("Dry-run: would update user '{$user->name}'.");
            }
            return Command::SUCCESS;
        }

        if ($email) $user->email = $email;
        if ($password) $user->pass = $password;

        foreach ($addRoles as $r) {
            $role = $roles->get((string)$r);
            if ($role && $role->id && !$user->hasRole($role)) {
                $user->addRole($role);
            }
        }
        foreach ($removeRoles as $r) {
            $role = $roles->get((string)$r);
            if ($role && $role->id && $user->hasRole($role)) {
                $user->removeRole($role);
            }
        }

        $user->save();

        if ($asJson) {
            $output->writeln(json_encode(['ok' => true, 'data' => $result + ['saved' => true]], JSON_UNESCAPED_SLASHES));
        } else {
            $io->success("Updated user '{$user->name}'.");
        }
        return Command::SUCCESS;
    }
}

