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
        $io = new SymfonyStyle($input, $output);
        $force = (bool)$input->getOption('force');
        $asJson = (bool)$input->getOption('json');

        if (!$force && !$asJson) {
            if (!$io->confirm('This will rollback ALL migrations and re-run them. Continue?', false)) {
                $io->note('Aborted.');
                return Command::SUCCESS;
            }
        }

        $repository = new MigrationRepository();
        $migrator = new Migrator($repository);

        // Phase 1: Reset
        $resetResult = $migrator->reset();

        if (!$asJson && !empty($resetResult['rolledBack'])) {
            $io->section('Rolling back');
            foreach ($resetResult['rolledBack'] as $file) {
                $io->writeln("  <comment>↩</comment> {$file}");
            }
        }

        if (!empty($resetResult['errors'])) {
            if ($asJson) {
                $output->writeln(json_encode(['ok' => false, 'data' => ['phase' => 'reset', 'result' => $resetResult]], JSON_UNESCAPED_SLASHES));
            } else {
                foreach ($resetResult['errors'] as $error) {
                    $io->error($error);
                }
            }
            return Command::FAILURE;
        }

        // Phase 2: Migrate
        $migrateResult = $migrator->runPending();

        if (!$asJson && !empty($migrateResult['applied'])) {
            $io->section('Migrating');
            foreach ($migrateResult['applied'] as $file) {
                $io->writeln("  <info>✓</info> {$file}");
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
                $io->error($error);
            }
            return Command::FAILURE;
        }

        $io->success("Refreshed: rolled back " . count($resetResult['rolledBack']) . ", applied " . count($migrateResult['applied']) . " migration(s).");
        return Command::SUCCESS;
    }
}
