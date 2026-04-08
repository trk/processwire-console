<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function Laravel\Prompts\table;

final class PermissionListCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('permission:list')
            ->setDescription('List all permissions in ProcessWire.')
            ->addOption('json', null, \Symfony\Component\Console\Input\InputOption::VALUE_NONE, 'JSON output');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $permissions = \ProcessWire\wire('permissions');
        $asJson = (bool)$input->getOption('json');
        $items = [];
        foreach ($permissions as $p) {
            $items[] = ['id' => $p->id, 'name' => $p->name, 'title' => (string)($p->title ?: '')];
        }
        if ($asJson) {
            $output->writeln(json_encode(['ok' => true, 'data' => ['items' => $items, 'total' => count($items)]], JSON_UNESCAPED_SLASHES));
        } else {
            table(['ID', 'Name', 'Title'], array_map(fn($r) => [$r['id'], $r['name'], $r['title'] ?: '-'], $items));
        }

        return Command::SUCCESS;
    }
}
