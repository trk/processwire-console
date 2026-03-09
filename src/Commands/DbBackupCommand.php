<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class DbBackupCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('db:backup')
            ->setDescription('Create a database backup.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $backup = \ProcessWire\wire('database')->backups();
        if (!$backup) {
            $io->error("Database backup tool not available.");
            return Command::FAILURE;
        }

        $file = $backup->backup();
        if ($file) {
            $io->success("Database backup created: {$file}");
        } else {
            $io->error("Database backup failed.");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
