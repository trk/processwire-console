<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class RoleGrantCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('role:grant')
            ->setDescription('Grant a permission to a role.')
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

        $already = $role->hasPermission($permission);
        $result = ['role' => $roleName, 'permission' => $permName, 'already' => $already, 'dryRun' => $dryRun];
        if ($dryRun) {
            if ($asJson) {
                $output->writeln(json_encode(['ok' => true, 'data' => $result], JSON_UNESCAPED_SLASHES));
            } else {
                $io->note("Dry-run: would grant '{$permName}' to role '{$roleName}'.");
            }
            return Command::SUCCESS;
        }

        if (!$already) {
            $role->addPermission($permission);
            $role->save();
        }

        if ($asJson) {
            $output->writeln(json_encode(['ok' => true, 'data' => $result + ['granted' => true]], JSON_UNESCAPED_SLASHES));
        } else {
            $io->success("Granted '{$permName}' to role '{$roleName}'.");
        }
        return Command::SUCCESS;
    }
}

