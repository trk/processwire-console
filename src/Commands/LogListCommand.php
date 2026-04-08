<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function Laravel\Prompts\warning;
use function Laravel\Prompts\table;

final class LogListCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('log:list')
            ->setDescription('List all available ProcessWire log files.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $logs = \ProcessWire\wire('log')->getFiles();
        
        if (empty($logs)) {
            warning("No log files found.");
            return Command::SUCCESS;
        }

        $headers = ['Log Name', 'Size', 'Modified'];
        $rows = [];
        foreach ($logs as $name => $fileInfo) {
            $size = number_format($fileInfo->getSize() / 1024, 2) . ' kB';
            $modified = date('Y-m-d H:i:s', $fileInfo->getMTime());
            $rows[] = [$name, $size, $modified];
        }

        table($headers, $rows);

        return Command::SUCCESS;
    }
}
