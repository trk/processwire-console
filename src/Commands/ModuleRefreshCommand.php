<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class ModuleRefreshCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('module:refresh')
            ->setDescription('Refresh modules (single or all).')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Optional module class name (refresh all if omitted)')
            ->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getOption('name') ? (string)$input->getOption('name') : '';
        $asJson = (bool)$input->getOption('json');

        $modules = \ProcessWire\wire('modules');
        if ($name) {
            $modules->refresh();
            $data = ['name' => $name, 'refreshed' => true];
        } else {
            $modules->refresh();
            $data = ['all' => true, 'refreshed' => true];
        }

        if ($asJson) {
            $output->writeln(json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_SLASHES));
        } else {
            if ($name) $io->success("Refreshed modules (including '{$name}').");
            else $io->success("Refreshed all modules.");
        }
        return Command::SUCCESS;
    }
}

