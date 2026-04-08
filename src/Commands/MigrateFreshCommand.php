<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Totoglu\ProcessWire\Console\Migration\Migrator;
use Totoglu\ProcessWire\Console\Migration\MigrationRepository;

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
        $io = new SymfonyStyle($input, $output);
        $force = (bool)$input->getOption('force');
        $asJson = (bool)$input->getOption('json');

        if (!$force && !$asJson) {
            if (!$io->confirm('This will drop the migrations table and re-run ALL migrations. Continue?', false)) {
                $io->note('Aborted.');
                return Command::SUCCESS;
            }
        }

        $repository = new MigrationRepository();
        $migrator = new Migrator($repository);

        // Phase 1: Drop table
        $repository->dropTable();
        if (!$asJson) {
            $io->writeln('  <comment>Dropped migrations table.</comment>');
        }

        // Phase 2: Re-run all
        $result = $migrator->runPending();

        if ($asJson) {
            $ok = empty($result['errors']);
            $output->writeln(json_encode(['ok' => $ok, 'data' => $result], JSON_UNESCAPED_SLASHES));
            return $ok ? Command::SUCCESS : Command::FAILURE;
        }

        foreach ($result['applied'] as $file) {
            $io->writeln("  <info>✓</info> {$file}");
        }

        if (!empty($result['errors'])) {
            foreach ($result['errors'] as $error) {
                $io->error($error);
            }
            return Command::FAILURE;
        }

        $io->success("Fresh migration: applied " . count($result['applied']) . " migration(s).");
        return Command::SUCCESS;
    }
}
