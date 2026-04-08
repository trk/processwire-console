<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class ModuleInstallCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('module:install')
            ->setDescription('Install a ProcessWire module by class name.')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Module class name (required)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write changes')
            ->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = (string)$input->getOption('name');
        $dryRun = (bool)$input->getOption('dry-run');
        $asJson = (bool)$input->getOption('json');

        if (!$name) {
            $io->error("Provide --name.");
            return Command::FAILURE;
        }

        $modules = \ProcessWire\wire('modules');
        $installed = $modules->isInstalled($name);
        $result = ['name' => $name, 'alreadyInstalled' => $installed, 'dryRun' => $dryRun];

        if ($dryRun) {
            if ($asJson) {
                $output->writeln(json_encode(['ok' => true, 'data' => $result], JSON_UNESCAPED_SLASHES));
            } else {
                $io->note("Dry-run: would install module '{$name}'.");
            }
            return Command::SUCCESS;
        }

        if ($installed) {
            if ($asJson) {
                $output->writeln(json_encode(['ok' => true, 'data' => $result], JSON_UNESCAPED_SLASHES));
            } else {
                $io->success("Module '{$name}' is already installed.");
            }
            return Command::SUCCESS;
        }

        try {
            $modules->install($name);
        } catch (\Throwable $e) {
            $msg = "Install failed for module '{$name}': " . $e->getMessage();
            if ($asJson) {
                $output->writeln(json_encode(['ok' => false, 'error' => ['code' => 'INSTALL_FAILED', 'message' => $msg]], JSON_UNESCAPED_SLASHES));
            } else {
                $io->error($msg);
            }
            return Command::FAILURE;
        }

        if ($asJson) {
            $output->writeln(json_encode(['ok' => true, 'data' => $result + ['installed' => true]], JSON_UNESCAPED_SLASHES));
        } else {
            $io->success("Installed module '{$name}'.");
        }
        return Command::SUCCESS;
    }
}

