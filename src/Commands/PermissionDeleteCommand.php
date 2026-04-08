<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class PermissionDeleteCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('permission:delete')
            ->setDescription('Delete a custom permission.')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Permission name (required)')
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

        $permissions = \ProcessWire\wire('permissions');
        $perm = $permissions->get($name);
        if (!$perm || !$perm->id) {
            $io->error("Permission not found: {$name}");
            return Command::FAILURE;
        }

        if (!$force && !$asJson && !$dryRun) {
            if (!$io->confirm("Delete permission '{$name}'?", false)) {
                $io->note("Aborted.");
                return Command::SUCCESS;
            }
        }

        $result = ['name' => $name, 'dryRun' => $dryRun];
        if ($dryRun) {
            if ($asJson) {
                $output->writeln(json_encode(['ok' => true, 'data' => $result], JSON_UNESCAPED_SLASHES));
            } else {
                $io->note("Dry-run: would delete permission '{$name}'.");
            }
            return Command::SUCCESS;
        }

        try {
            $permissions->delete($perm);
        } catch (\Throwable $e) {
            $msg = "Delete failed for permission '{$name}': " . $e->getMessage();
            if ($asJson) {
                $output->writeln(json_encode(['ok' => false, 'error' => ['code' => 'DELETE_FAILED', 'message' => $msg]], JSON_UNESCAPED_SLASHES));
            } else {
                $io->error($msg);
            }
            return Command::FAILURE;
        }

        if ($asJson) {
            $output->writeln(json_encode(['ok' => true, 'data' => $result + ['deleted' => true]], JSON_UNESCAPED_SLASHES));
        } else {
            $io->success("Deleted permission '{$name}'.");
        }
        return Command::SUCCESS;
    }
}

