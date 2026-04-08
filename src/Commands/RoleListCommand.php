<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class RoleListCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('role:list')
            ->setDescription('List all roles in ProcessWire.')
            ->addOption('json', null, \Symfony\Component\Console\Input\InputOption::VALUE_NONE, 'JSON output');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $roles = \ProcessWire\wire('roles');

        $asJson = (bool)$input->getOption('json');
        $items = [];
        foreach ($roles as $role) {
            $permissions = [];
            foreach ($role->permissions as $p) {
                $permissions[] = $p->name;
            }
            $items[] = ['id' => $role->id, 'name' => $role->name, 'permissions' => $permissions];
        }

        if ($asJson) {
            $output->writeln(json_encode(['ok' => true, 'data' => ['items' => $items, 'total' => count($items)]], JSON_UNESCAPED_SLASHES));
        } else {
            $tableData = array_map(fn($r) => [$r['id'], $r['name'], implode(', ', $r['permissions'])], $items);
            $io->table(['ID', 'Name', 'Permissions'], $tableData);
        }

        return Command::SUCCESS;
    }
}
