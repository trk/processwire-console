<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Traits\InteractWithProcessWire;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\confirm;

final class LogClearCommand extends Command
{
    use InteractWithProcessWire;
    protected function configure(): void
    {
        $this
            ->setName('log:clear')
            ->setDescription('Clear a ProcessWire log file.')
            ->addArgument('name', InputArgument::IS_ARRAY, 'The name of the logs (e.g. errors messages)', [])
            ->addOption('json', null, InputOption::VALUE_NONE, 'JSON output')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip interactive confirmations');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $names = (array) $input->getArgument('name');
        
        if (empty($names)) {
            $names = $this->multiselectLogs('Select log files to clear');
            if (empty($names)) {
                error('No log files selected or available.');
                return Command::FAILURE;
            }
        }

        $asJson = (bool)$input->getOption('json');
        $force = (bool)$input->getOption('force');

        if (!$force && !$asJson) {
            $list = implode(', ', $names);
            if (!confirm("Clear log files '{$list}'?", false)) {
                note("Aborted.");
                return Command::SUCCESS;
            }
        }

        $results = [];
        $allOk = true;

        foreach ($names as $name) {
            $file = basename((string)$name);
            $path = \ProcessWire\wire('config')->paths->logs . $file . '.txt';
            if (!is_file($path)) {
                if (!$asJson) error("Log file not found: {$path}");
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
            $allOk ? info("Cleared selected logs.") : error("Failed to clear some logs.");
        }
        return $allOk ? Command::SUCCESS : Command::FAILURE;
    }
}

