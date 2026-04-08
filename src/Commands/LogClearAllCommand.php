<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\confirm;

final class LogClearAllCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('log:clear-all')
            ->setDescription('Clear all ProcessWire log files.')
            ->addOption('json', null, InputOption::VALUE_NONE, 'JSON output')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip interactive confirmations');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $asJson = (bool)$input->getOption('json');
        $force = (bool)$input->getOption('force');

        $log = \ProcessWire\wire('log');
        $logs = $log->getFiles();

        if (empty($logs)) {
            if (!$asJson) note('No log files available to clear.');
            if ($asJson) $output->writeln(json_encode(['ok' => true, 'data' => []]));
            return Command::SUCCESS;
        }

        if (!$force && !$asJson) {
            if (!confirm("Clear " . count($logs) . " log files?", false)) {
                note("Aborted.");
                return Command::SUCCESS;
            }
        }

        $results = [];
        $allOk = true;

        foreach ($logs as $name => $fileInfo) {
            $file = basename((string)$name);
            $path = \ProcessWire\wire('config')->paths->logs . $file . '.txt';
            
            if (!is_file($path)) {
                $results[$file] = false;
                $allOk = false;
                continue;
            }

            $ok = @file_put_contents($path, '') !== false;
            $results[$file] = $ok;
            if (!$ok) $allOk = false;
        }

        if ($asJson) {
            $output->writeln(json_encode(['ok' => $allOk, 'data' => $results], JSON_UNESCAPED_SLASHES));
        } else {
            $allOk ? info("Cleared all logs.") : error("Failed to clear some logs.");
        }
        return $allOk ? Command::SUCCESS : Command::FAILURE;
    }
}
