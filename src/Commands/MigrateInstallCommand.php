<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
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
        $io = new SymfonyStyle($input, $output);
        $asJson = (bool)$input->getOption('json');

        $repository = new MigrationRepository();

        if ($repository->tableExists()) {
            if ($asJson) {
                $output->writeln(json_encode(['ok' => true, 'data' => ['created' => false, 'message' => 'Migration table already exists.']], JSON_UNESCAPED_SLASHES));
            } else {
                $io->note('Migration table already exists.');
            }
            return Command::SUCCESS;
        }

        $repository->ensureTable();

        if ($asJson) {
            $output->writeln(json_encode(['ok' => true, 'data' => ['created' => true]], JSON_UNESCAPED_SLASHES));
        } else {
            $io->success('Migration table created.');
        }

        return Command::SUCCESS;
    }
}
