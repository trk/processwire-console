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

final class MigrateResetCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('migrate:reset')
            ->setDescription('Rollback all applied migrations.')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip interactive confirmations')
            ->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = (bool)$input->getOption('force');
        $asJson = (bool)$input->getOption('json');

        $migrator = new Migrator(new MigrationRepository());

        if (!$force && !$asJson) {
            if (!$io->confirm('This will rollback ALL applied migrations. Continue?', false)) {
                $io->note('Aborted.');
                return Command::SUCCESS;
            }
        }

        $result = $migrator->reset();

        if ($asJson) {
            $ok = empty($result['errors']);
            $output->writeln(json_encode(['ok' => $ok, 'data' => $result], JSON_UNESCAPED_SLASHES));
            return $ok ? Command::SUCCESS : Command::FAILURE;
        }

        if (empty($result['rolledBack']) && empty($result['errors'])) {
            $io->note('Nothing to reset.');
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

        $io->success("Reset " . count($result['rolledBack']) . " migration(s).");
        return Command::SUCCESS;
    }
}
