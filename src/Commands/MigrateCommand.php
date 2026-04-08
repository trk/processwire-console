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

final class MigrateCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('migrate')
            ->setDescription('Run all pending migrations.')
            ->addOption('step', null, InputOption::VALUE_REQUIRED, 'Number of migrations to run (default: all)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show pending migrations without applying')
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

        $migrator = new Migrator(new MigrationRepository());
        $pending = $migrator->getPending();

        if (empty($pending)) {
            if ($asJson) {
                $output->writeln(json_encode(['ok' => true, 'data' => ['applied' => [], 'message' => 'Nothing to migrate.']], JSON_UNESCAPED_SLASHES));
            } else {
                $io->success('Nothing to migrate.');
            }
            return Command::SUCCESS;
        }

        if ($steps > 0) {
            $pending = array_slice($pending, 0, $steps);
        }

        if ($dryRun) {
            $data = ['pending' => $pending, 'count' => count($pending)];
            if ($asJson) {
                $output->writeln(json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_SLASHES));
            } else {
                $io->note("Dry-run: " . count($pending) . " migration(s) would be applied:");
                foreach ($pending as $file) {
                    $io->writeln("  <info>→</info> {$file}");
                }
            }
            return Command::SUCCESS;
        }

        if (!$force && !$asJson) {
            $io->note(count($pending) . " pending migration(s):");
            foreach ($pending as $file) {
                $io->writeln("  <info>→</info> {$file}");
            }
            if (!$io->confirm('Apply these migrations?', true)) {
                $io->note('Aborted.');
                return Command::SUCCESS;
            }
        }

        $result = $migrator->runPending($steps);

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
            $io->warning("Applied " . count($result['applied']) . " migration(s) before error.");
            return Command::FAILURE;
        }

        $io->success("Applied " . count($result['applied']) . " migration(s).");
        return Command::SUCCESS;
    }
}
