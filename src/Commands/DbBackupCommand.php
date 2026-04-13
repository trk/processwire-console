<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;

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

        $backup = \ProcessWire\wire('database')->backups();
        if (!$backup) {
            error("Database backup tool not available.");
            return Command::FAILURE;
        }

        info('Creating database backup...');
        $file = $backup->backup();

        if ($file) {
            info("Database backup created: {$file}");
        } else {
            error("Database backup failed.");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
