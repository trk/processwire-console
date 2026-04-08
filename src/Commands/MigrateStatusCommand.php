<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Totoglu\ProcessWire\Console\Migration\Migrator;
use Totoglu\ProcessWire\Console\Migration\MigrationRepository;

final class MigrateStatusCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('migrate:status')
            ->setDescription('Show the status of each migration.')
            ->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $asJson = (bool)$input->getOption('json');

        $migrator = new Migrator(new MigrationRepository());
        $status = $migrator->getStatus();

        if (empty($status)) {
            if ($asJson) {
                $output->writeln(json_encode(['ok' => true, 'data' => ['items' => [], 'total' => 0]], JSON_UNESCAPED_SLASHES));
            } else {
                $io->note('No migration files found in ' . $migrator->getMigrationsPath());
            }
            return Command::SUCCESS;
        }

        if ($asJson) {
            $output->writeln(json_encode(['ok' => true, 'data' => ['items' => $status, 'total' => count($status)]], JSON_UNESCAPED_SLASHES));
            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Migration', 'Status', 'Batch']);

        $appliedCount = 0;
        $pendingCount = 0;

        foreach ($status as $row) {
            $statusLabel = $row['status'] === 'applied'
                ? '<info>Applied</info>'
                : '<comment>Pending</comment>';
            $batchLabel = $row['batch'] !== null ? (string)$row['batch'] : '-';

            $table->addRow([$row['name'], $statusLabel, $batchLabel]);

            if ($row['status'] === 'applied') {
                $appliedCount++;
            } else {
                $pendingCount++;
            }
        }

        $table->render();
        $io->note("{$appliedCount} applied, {$pendingCount} pending.");

        return Command::SUCCESS;
    }
}
