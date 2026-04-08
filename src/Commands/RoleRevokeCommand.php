<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class RoleRevokeCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('role:revoke')
            ->setDescription('Revoke a permission from a role.')
            ->addOption('role', null, InputOption::VALUE_REQUIRED, 'Role name (required)')
            ->addOption('permission', null, InputOption::VALUE_REQUIRED, 'Permission name (required)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write changes')
            ->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $roleName = (string)$input->getOption('role');
        $permName = (string)$input->getOption('permission');
        $dryRun = (bool)$input->getOption('dry-run');
        $asJson = (bool)$input->getOption('json');

        if (!$roleName || !$permName) {
            $io->error("Provide --role and --permission.");
            return Command::FAILURE;
        }
        $roles = \ProcessWire\wire('roles');
        $permissions = \ProcessWire\wire('permissions');
        $role = $roles->get($roleName);
        $permission = $permissions->get($permName);
        if (!$role || !$role->id) {
            $io->error("Role not found: {$roleName}");
            return Command::FAILURE;
        }
        if (!$permission || !$permission->id) {
            $io->error("Permission not found: {$permName}");
            return Command::FAILURE;
        }

        $has = $role->hasPermission($permission);
        $result = ['role' => $roleName, 'permission' => $permName, 'has' => $has, 'dryRun' => $dryRun];
        if ($dryRun) {
            if ($asJson) {
                $output->writeln(json_encode(['ok' => true, 'data' => $result], JSON_UNESCAPED_SLASHES));
            } else {
                $io->note("Dry-run: would revoke '{$permName}' from role '{$roleName}'.");
            }
            return Command::SUCCESS;
        }

        if ($has) {
            $role->removePermission($permission);
            $role->save();
        }

        if ($asJson) {
            $output->writeln(json_encode(['ok' => true, 'data' => $result + ['revoked' => true]], JSON_UNESCAPED_SLASHES));
        } else {
            $io->success("Revoked '{$permName}' from role '{$roleName}'.");
        }
        return Command::SUCCESS;
    }
}

