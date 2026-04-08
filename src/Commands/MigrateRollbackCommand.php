<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Totoglu\Console\Migration\Migrator;
use Totoglu\Console\Migration\MigrationRepository;

final class MigrateRollbackCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('migrate:rollback')
            ->setDescription('Rollback the last batch of migrations.')
            ->addOption('step', null, InputOption::VALUE_REQUIRED, 'Number of individual migrations to rollback (instead of batch)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be rolled back')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip interactive confirmations')
            ->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $steps = $input->getOption('step') ? (int)$input->getOption('step') : 0;
        $dryRun = (bool)$input->getOption('dry-run');
        $force = (bool)$input->getOption('force');
        $asJson = (bool)$input->getOption('json');

        $repository = new MigrationRepository();
        $migrator = new Migrator($repository);

        if ($dryRun) {
            if ($steps > 0) {
                $toRollback = $repository->getLastMigrations($steps);
            } else {
                $lastBatch = $repository->getLastBatchNumber();
                $toRollback = $lastBatch > 0 ? $repository->getMigrationsForBatch($lastBatch) : [];
            }

            $names = array_column($toRollback, 'migration');
            $data = ['toRollback' => $names, 'count' => count($names)];

            if ($asJson) {
                $output->writeln(json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_SLASHES));
            } else {
                if (empty($names)) {
                    $io->note('Nothing to rollback.');
                } else {
                    $io->note("Dry-run: " . count($names) . " migration(s) would be rolled back:");
                    foreach ($names as $name) {
                        $io->writeln("  <comment>↩</comment> {$name}");
                    }
                }
            }
            return Command::SUCCESS;
        }

        if (!$force && !$asJson) {
            if (!$io->confirm('Rollback migrations?', false)) {
                $io->note('Aborted.');
                return Command::SUCCESS;
            }
        }

        $result = $steps > 0
            ? $migrator->rollbackSteps($steps)
            : $migrator->rollbackLastBatch();

        if ($asJson) {
            $ok = empty($result['errors']);
            $output->writeln(json_encode(['ok' => $ok, 'data' => $result], JSON_UNESCAPED_SLASHES));
            return $ok ? Command::SUCCESS : Command::FAILURE;
        }

        if (empty($result['rolledBack']) && empty($result['errors'])) {
            $io->note('Nothing to rollback.');
            return Command::SUCCESS;
        }

        foreach ($result['rolledBack'] as $file) {
            $io->writeln("  <comment>↩</comment> {$file}");
        }

        if (!empty($result['errors'])) {
            foreach ($result['errors'] as $error) {
                $io->error($error);
            }
            return Command::FAILURE;
        }

        $io->success("Rolled back " . count($result['rolledBack']) . " migration(s).");
        return Command::SUCCESS;
    }
}
