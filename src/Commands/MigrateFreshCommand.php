<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Migration\Migrator;
use Totoglu\Console\Migration\MigrationRepository;

final class MigrateFreshCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('migrate:fresh')
            ->setDescription('Drop the migrations table and re-run all migrations from scratch.')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip interactive confirmations')
            ->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $force = (bool)$input->getOption('force');
        $asJson = (bool)$input->getOption('json');

        if (!$force && !$asJson) {
            if (!\Laravel\Prompts\confirm('This will drop the migrations table and re-run ALL migrations. Continue?', default: false)) {
                \Laravel\Prompts\note('Aborted.');
                return Command::SUCCESS;
            }
        }

        $repository = new MigrationRepository();
        $migrator = new Migrator($repository);

        // Phase 1: Drop table
        if (!$asJson) {
            \Laravel\Prompts\spin(fn() => $repository->dropTable(), 'Dropping migrations table...');
            \Laravel\Prompts\info('Dropped migrations table.');
        } else {
            $repository->dropTable();
        }

        // Phase 2: Re-run all
        $result = $asJson 
            ? $migrator->runPending() 
            : \Laravel\Prompts\spin(fn() => $migrator->runPending(), 'Running all migrations...');

        if ($asJson) {
            $ok = empty($result['errors']);
            $output->writeln(json_encode(['ok' => $ok, 'data' => $result], JSON_UNESCAPED_SLASHES));
            return $ok ? Command::SUCCESS : Command::FAILURE;
        }

        foreach ($result['applied'] as $file) {
            \Laravel\Prompts\info("  ✓ {$file}");
        }

        if (!empty($result['errors'])) {
            foreach ($result['errors'] as $error) {
                \Laravel\Prompts\error($error);
            }
            return Command::FAILURE;
        }

        \Laravel\Prompts\info("Fresh migration: applied " . count($result['applied']) . " migration(s).");
        return Command::SUCCESS;
    }
}
