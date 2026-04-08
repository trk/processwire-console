<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

final class BackupListCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('backup:list')
            ->setDescription('List database backup files in site/assets/backups/database.')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Limit number of entries', '50');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = (int)$input->getOption('limit');

        $dir = \ProcessWire\wire('config')->paths->assets . 'backups/database/';
        if (!is_dir($dir)) {
            warning("Directory not found: {$dir}");
            return Command::SUCCESS;
        }

        $files = array_values(array_filter(scandir($dir) ?: [], fn($f) => $f !== '.' && $f !== '..' && is_file($dir . $f)));
        usort($files, fn($a, $b) => filemtime($dir . $b) <=> filemtime($dir . $a));
        $files = array_slice($files, 0, $limit);

        if (!$files) {
            warning("No backup files found.");
            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($files as $f) {
            $rows[] = [$f, (int)(filesize($dir . $f) / 1024), date('Y-m-d H:i', (int)filemtime($dir . $f))];
        }
        
        table(
            ['File', 'Size (KB)', 'Modified'],
            $rows
        );
        
        return Command::SUCCESS;
    }
}

