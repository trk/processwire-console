<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\spin;

final class DbRestoreCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('db:restore')
            ->setDescription('Restore database from a .sql backup file.')
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'Path to .sql file (required)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only validate file access without executing')
            ->addOption('json', null, InputOption::VALUE_NONE, 'JSON output')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip interactive confirmations');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = (string)$input->getOption('file');
        $dryRun = (bool)$input->getOption('dry-run');
        $asJson = (bool)$input->getOption('json');
        $force = (bool)$input->getOption('force');

        if (!$file) {
            error("Provide --file.");
            return Command::FAILURE;
        }
        if (!is_file($file) || !is_readable($file)) {
            error("SQL file not readable: {$file}");
            return Command::FAILURE;
        }
        if (str_ends_with(strtolower($file), '.gz')) {
            error(".gz not supported in this command. Provide an extracted .sql file.");
            return Command:: FAILURE;
        }

        if ($dryRun) {
            $data = ['file' => realpath($file), 'validated' => true];
            if ($asJson) $output->writeln(json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_SLASHES));
            else info("Validated SQL file: " . realpath($file));
            return Command::SUCCESS;
        }

        if (!$force && !$asJson) {
            if (!confirm("This will run SQL statements on your database. Continue?", false)) {
                note("Aborted.");
                return Command::SUCCESS;
            }
        }

        $db = \ProcessWire\wire('database');

        try {
            $backup = $db->backups();
            if (!$backup) {
                error("Database backup tool not available.");
                return Command::FAILURE;
            }
            if ($asJson) {
                $result = $backup->restore($file);
            } else {
                info("Restoring database from {$file}...");
                $result = $backup->restore($file);
            }
            if (!$result) {
                $msg = "Restore returned no result — check the SQL file.";
                $errors = $backup->errors();
                if ($errors) {
                    $msg .= "\n" . implode("\n", $errors);
                }
                if ($asJson) $output->writeln(json_encode(['ok' => false, 'error' => ['code' => 'RESTORE_FAILED', 'message' => $msg]], JSON_UNESCAPED_SLASHES));
                else error($msg);
                return Command::FAILURE;
            }
        } catch (\Throwable $e) {
            $msg = "Restore failed: " . $e->getMessage();
            if ($asJson) $output->writeln(json_encode(['ok' => false, 'error' => ['code' => 'RESTORE_FAILED', 'message' => $msg]], JSON_UNESCAPED_SLASHES));
            else error($msg);
            return Command::FAILURE;
        }

        $data = ['file' => realpath($file), 'restored' => true];
        if ($asJson) $output->writeln(json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_SLASHES));
        else info("Database restored from {$file}.");
        return Command::SUCCESS;
    }
}

