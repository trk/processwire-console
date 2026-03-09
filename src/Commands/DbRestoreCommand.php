<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

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
        $io = new SymfonyStyle($input, $output);
        $file = (string)$input->getOption('file');
        $dryRun = (bool)$input->getOption('dry-run');
        $asJson = (bool)$input->getOption('json');
        $force = (bool)$input->getOption('force');

        if (!$file) {
            $io->error("Provide --file.");
            return Command::FAILURE;
        }
        if (!is_file($file) || !is_readable($file)) {
            $io->error("SQL file not readable: {$file}");
            return Command::FAILURE;
        }
        if (str_ends_with(strtolower($file), '.gz')) {
            $io->error(".gz not supported in this command. Provide an extracted .sql file.");
            return Command:: FAILURE;
        }

        if ($dryRun) {
            $data = ['file' => realpath($file), 'validated' => true];
            if ($asJson) $output->writeln(json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_SLASHES));
            else $io->success("Validated SQL file: " . realpath($file));
            return Command::SUCCESS;
        }

        if (!$force && !$asJson) {
            if (!$io->confirm("This will run SQL statements on your database. Continue?", false)) {
                $io->note("Aborted.");
                return Command::SUCCESS;
            }
        }

        $db = \ProcessWire\wire('database');
        $sql = file_get_contents($file) ?: '';
        if ($sql === '') {
            $io->error("SQL file is empty.");
            return Command::FAILURE;
        }

        try {
            $db->beginTransaction();
            $statements = preg_split('/;\s*(\r?\n)+/', $sql);
            foreach ($statements as $stmt) {
                $stmt = trim($stmt);
                if ($stmt === '' || str_starts_with($stmt, '--') || str_starts_with($stmt, '/*')) continue;
                $db->exec($stmt);
            }
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            $msg = "Restore failed: " . $e->getMessage();
            if ($asJson) $output->writeln(json_encode(['ok' => false, 'error' => ['code' => 'RESTORE_FAILED', 'message' => $msg]], JSON_UNESCAPED_SLASHES));
            else $io->error($msg);
            return Command::FAILURE;
        }

        $data = ['file' => realpath($file), 'restored' => true];
        if ($asJson) $output->writeln(json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_SLASHES));
        else $io->success("Database restored from {$file}.");
        return Command::SUCCESS;
    }
}

