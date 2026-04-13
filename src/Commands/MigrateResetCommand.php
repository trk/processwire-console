<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
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
        $force = (bool)$input->getOption('force');
        $asJson = (bool)$input->getOption('json');

        $migrator = new Migrator(new MigrationRepository());

        if (!$force && !$asJson) {
            if (!\Laravel\Prompts\confirm('This will rollback ALL applied migrations. Continue?', default: false)) {
                \Laravel\Prompts\note('Aborted.');
                return Command::SUCCESS;
            }
        }

        if (!$asJson) {
            \Laravel\Prompts\info('Resetting all migrations...');
        }
        $result = $migrator->reset();

        if ($asJson) {
            $ok = empty($result['errors']);
            $output->writeln(json_encode(['ok' => $ok, 'data' => $result], JSON_UNESCAPED_SLASHES));
            return $ok ? Command::SUCCESS : Command::FAILURE;
        }

        if (empty($result['rolledBack']) && empty($result['errors'])) {
            \Laravel\Prompts\note('Nothing to reset.');
            return Command::SUCCESS;
        }

        foreach ($result['rolledBack'] as $file) {
            \Laravel\Prompts\warning("  ↩ {$file}");
        }

        if (!empty($result['errors'])) {
            foreach ($result['errors'] as $error) {
                \Laravel\Prompts\error($error);
            }
            return Command::FAILURE;
        }

        \Laravel\Prompts\info("Reset " . count($result['rolledBack']) . " migration(s).");
        return Command::SUCCESS;
    }
}
