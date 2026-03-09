<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class ModuleDisableCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('module:disable')
            ->setDescription('Disable (uninstall) a ProcessWire module by class name.')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Module class name (required)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write changes')
            ->addOption('json', null, InputOption::VALUE_NONE, 'JSON output')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip interactive confirmations');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = (string)$input->getOption('name');
        $dryRun = (bool)$input->getOption('dry-run');
        $asJson = (bool)$input->getOption('json');
        $force = (bool)$input->getOption('force');

        if (!$name) {
            $io->error("Provide --name.");
            return Command::FAILURE;
        }
        $modules = \ProcessWire\wire('modules');
        $installed = $modules->isInstalled($name);
        if (!$installed) {
            if ($asJson) $output->writeln(json_encode(['ok' => true, 'data' => ['name' => $name, 'alreadyDisabled' => true]], JSON_UNESCAPED_SLASHES));
            else $io->warning("Module '{$name}' is not enabled.");
            return Command::SUCCESS;
        }
        if (!$force && !$asJson && !$dryRun) {
            if (!$io->confirm("Disable module '{$name}'?", false)) {
                $io->note("Aborted.");
                return Command::SUCCESS;
            }
        }
        $result = ['name' => $name, 'dryRun' => $dryRun];
        if ($dryRun) {
            if ($asJson) $output->writeln(json_encode(['ok' => true, 'data' => $result], JSON_UNESCAPED_SLASHES));
            else $io->note("Dry-run: would disable module '{$name}'.");
            return Command::SUCCESS;
        }
        try {
            $modules->uninstall($name);
        } catch (\Throwable $e) {
            $msg = "Disable failed for module '{$name}': " . $e->getMessage();
            if ($asJson) $output->writeln(json_encode(['ok' => false, 'error' => ['code' => 'DISABLE_FAILED', 'message' => $msg]], JSON_UNESCAPED_SLASHES));
            else $io->error($msg);
            return Command::FAILURE;
        }
        if ($asJson) $output->writeln(json_encode(['ok' => true, 'data' => $result + ['disabled' => true]], JSON_UNESCAPED_SLASHES));
        else $io->success("Disabled module '{$name}'.");
        return Command::SUCCESS;
    }
}

