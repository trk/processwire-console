<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function Laravel\Prompts\text;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;

final class RoleCreateCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('role:create')
            ->setDescription('Create a new role.')
            ->addArgument('name', InputArgument::OPTIONAL, 'The name of the role')
            ->addOption('permissions', null, InputOption::VALUE_OPTIONAL, 'Comma-separated permission names to grant')
            ->addOption('interactive', 'i', InputOption::VALUE_NONE, 'Prompt to select permissions to grant');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        
        if (!$name) {
            $name = text(
                label: 'Role name',
                required: true
            );
        }

        $permsOpt = $input->getOption('permissions') ? (string)$input->getOption('permissions') : '';
        $wantInteractive = (bool)$input->getOption('interactive');

        $existing = \ProcessWire\wire('roles')->get($name);
        if ($existing && $existing->id) {
            error("Role '{$name}' already exists.");
            return Command::FAILURE;
        }

        $role = \ProcessWire\wire('roles')->add($name);
        $role->save();

        $granted = [];
        $permissionsApi = \ProcessWire\wire('permissions');
        if ($wantInteractive && $input->isInteractive()) {
            $all = [];
            foreach ($permissionsApi as $p) $all[] = $p->name;
            $choices = multiselect('Grant permissions to this role?', $all, required: false);
            if ($choices) {
                foreach ($choices as $perm) {
                    $p = $permissionsApi->get($perm);
                    if ($p && $p->id && !$role->hasPermission($p)) {
                        $role->addPermission($p);
                        $granted[] = $perm;
                    }
                }
                $role->save();
            }
        } elseif ($permsOpt) {
            foreach (explode(',', $permsOpt) as $perm) {
                $perm = trim($perm);
                if ($perm === '') continue;
                $p = $permissionsApi->get($perm);
                if ($p && $p->id && !$role->hasPermission($p)) {
                    $role->addPermission($p);
                    $granted[] = $perm;
                }
            }
            if ($granted) $role->save();
        }

        $msg = "Role '{$name}' created.";
        if ($granted) $msg .= " Granted: " . implode(', ', $granted) . ".";
        info($msg);

        return Command::SUCCESS;
    }
}
