<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Migration\Migrator;
use Totoglu\Console\Migration\MigrationRepository;

final class MigrateRefreshCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('migrate:refresh')
            ->setDescription('Reset all migrations and re-run them (rollback + migrate).')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip interactive confirmations')
            ->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $force = (bool)$input->getOption('force');
        $asJson = (bool)$input->getOption('json');

        if (!$force && !$asJson) {
            if (!\Laravel\Prompts\confirm('This will rollback ALL migrations and re-run them. Continue?', default: false)) {
                \Laravel\Prompts\note('Aborted.');
                return Command::SUCCESS;
            }
        }

        $repository = new MigrationRepository();
        $migrator = new Migrator($repository);

        // Phase 1: Reset
        $resetResult = $asJson
            ? $migrator->reset()
            : \Laravel\Prompts\spin(fn() => $migrator->reset(), 'Rolling back all migrations...');

        if (!$asJson && !empty($resetResult['rolledBack'])) {
            \Laravel\Prompts\note('Rolling back');
            foreach ($resetResult['rolledBack'] as $file) {
                \Laravel\Prompts\warning("  ↩ {$file}");
            }
        }

        if (!empty($resetResult['errors'])) {
            if ($asJson) {
                $output->writeln(json_encode(['ok' => false, 'data' => ['phase' => 'reset', 'result' => $resetResult]], JSON_UNESCAPED_SLASHES));
            } else {
                foreach ($resetResult['errors'] as $error) {
                    \Laravel\Prompts\error($error);
                }
            }
            return Command::FAILURE;
        }

        // Phase 2: Migrate
        $migrateResult = $asJson
            ? $migrator->runPending()
            : \Laravel\Prompts\spin(fn() => $migrator->runPending(), 'Running all migrations...');

        if (!$asJson && !empty($migrateResult['applied'])) {
            \Laravel\Prompts\note('Migrating');
            foreach ($migrateResult['applied'] as $file) {
                \Laravel\Prompts\info("  ✓ {$file}");
            }
        }

        if ($asJson) {
            $ok = empty($migrateResult['errors']);
            $output->writeln(json_encode([
                'ok' => $ok,
                'data' => [
                    'rolledBack' => $resetResult['rolledBack'],
                    'applied' => $migrateResult['applied'],
                    'errors' => $migrateResult['errors'],
                ],
            ], JSON_UNESCAPED_SLASHES));
            return $ok ? Command::SUCCESS : Command::FAILURE;
        }

        if (!empty($migrateResult['errors'])) {
            foreach ($migrateResult['errors'] as $error) {
                \Laravel\Prompts\error($error);
            }
            return Command::FAILURE;
        }

        \Laravel\Prompts\info("Refreshed: rolled back " . count($resetResult['rolledBack']) . ", applied " . count($migrateResult['applied']) . " migration(s).");
        return Command::SUCCESS;
    }
}
