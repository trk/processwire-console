<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class LogsClearCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('logs:clear')
            ->setDescription('Clear a ProcessWire log file.')
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'Log file name without extension', 'errors')
            ->addOption('json', null, InputOption::VALUE_NONE, 'JSON output')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip interactive confirmations');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $file = (string)$input->getOption('file');
        $asJson = (bool)$input->getOption('json');
        $force = (bool)$input->getOption('force');

        $path = \ProcessWire\wire('config')->paths->logs . $file . '.txt';
        if (!is_file($path)) {
            $io->error("Log file not found: {$path}");
            return Command::FAILURE;
        }

        if (!$force && !$asJson) {
            if (!$io->confirm("Clear log file '{$file}'?", false)) {
                $io->note("Aborted.");
                return Command::SUCCESS;
            }
        }

        $ok = @file_put_contents($path, '');
        $data = ['file' => $file, 'cleared' => (bool)$ok];
        if ($asJson) {
            $output->writeln(json_encode(['ok' => (bool)$ok, 'data' => $data], JSON_UNESCAPED_SLASHES));
        } else {
            $ok ? $io->success("Cleared log '{$file}'.") : $io->error("Failed to clear log.");
        }
        return $ok ? Command::SUCCESS : Command::FAILURE;
    }
}

