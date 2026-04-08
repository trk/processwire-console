<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class BackupPurgeCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('backup:purge')
            ->setDescription('Purge database backups older than N days.')
            ->addOption('days', null, InputOption::VALUE_REQUIRED, 'Delete backups older than this many days', '30')
            ->addOption('json', null, InputOption::VALUE_NONE, 'JSON output')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip interactive confirmations');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = (int)$input->getOption('days');
        $asJson = (bool)$input->getOption('json');
        $force = (bool)$input->getOption('force');

        $dir = \ProcessWire\wire('config')->paths->assets . 'backups/database/';
        if (!is_dir($dir)) {
            $io->warning("Directory not found: {$dir}");
            return Command::SUCCESS;
        }

        $threshold = time() - ($days * 86400);
        $candidates = [];
        foreach (scandir($dir) ?: [] as $f) {
            if ($f === '.' || $f === '..') continue;
            $path = $dir . $f;
            if (is_file($path) && filemtime($path) < $threshold) $candidates[] = $path;
        }

        if (!$force && !$asJson) {
            if (!$io->confirm("Delete " . count($candidates) . " file(s) older than {$days} days?", false)) {
                $io->note("Aborted.");
                return Command::SUCCESS;
            }
        }

        $deleted = 0;
        foreach ($candidates as $p) {
            if (@unlink($p)) $deleted++;
        }

        $data = ['deleted' => $deleted, 'days' => $days];
        if ($asJson) {
            $output->writeln(json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_SLASHES));
        } else {
            $io->success("Deleted {$deleted} backup file(s) older than {$days} days.");
        }
        return Command::SUCCESS;
    }
}

