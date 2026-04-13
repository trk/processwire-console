<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Migration\MigrationRepository;

final class MigrateInstallCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('migrate:install')
            ->setDescription('Create the migration tracking table.')
            ->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $asJson = (bool)$input->getOption('json');

        $repository = new MigrationRepository();

        if ($repository->tableExists()) {
            if ($asJson) {
                $output->writeln(json_encode(['ok' => true, 'data' => ['created' => false, 'message' => 'Migration table already exists.']], JSON_UNESCAPED_SLASHES));
            } else {
                \Laravel\Prompts\note('Migration table already exists.');
            }
            return Command::SUCCESS;
        }

        if (!$asJson) {
            \Laravel\Prompts\info('Creating migration table...');
            $repository->ensureTable();
            \Laravel\Prompts\info('Migration table created.');
        } else {
            $repository->ensureTable();
            $output->writeln(json_encode(['ok' => true, 'data' => ['created' => true]], JSON_UNESCAPED_SLASHES));
        }

        return Command::SUCCESS;
    }
}
