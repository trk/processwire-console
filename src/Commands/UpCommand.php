<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class UpCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('up')
            ->setDescription('Bring the application out of maintenance mode');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $wire = \ProcessWire\wire();
        if (!$wire) {
            \Laravel\Prompts\error("ProcessWire environment not found.");
            return Command::FAILURE;
        }

        try {
            $file = $wire->config->paths->assets . 'down.json';

            if (!file_exists($file)) {
                \Laravel\Prompts\info('Application is already up.');
                return Command::SUCCESS;
            }

            @unlink($file);

            \Laravel\Prompts\info('Application is now live.');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            \Laravel\Prompts\error('Failed to bring application out of maintenance mode: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
