<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class LogsTailCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('logs:tail')
            ->setDescription('Tail a ProcessWire log file (like errors).')
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'Log file name without extension', 'errors')
            ->addOption('lines', null, InputOption::VALUE_REQUIRED, 'Number of lines to show from end', '200')
            ->addOption('follow', 'f', InputOption::VALUE_NONE, 'Follow appended output');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $file = (string)$input->getOption('file');
        $lines = (int)$input->getOption('lines');
        $follow = (bool)$input->getOption('follow');

        $path = \ProcessWire\wire('config')->paths->logs . $file . '.txt';
        if (!is_file($path)) {
            $io->error("Log file not found: {$path}");
            return Command::FAILURE;
        }

        $content = @file($path, FILE_IGNORE_NEW_LINES);
        if ($content === false) $content = [];
        $slice = array_slice($content, -$lines);
        foreach ($slice as $line) {
            $output->writeln($line);
        }

        if (!$follow) return Command::SUCCESS;

        $pos = filesize($path);
        while (true) {
            clearstatcache(true, $path);
            $size = filesize($path);
            if ($size > $pos) {
                $fp = fopen($path, 'r');
                if ($fp) {
                    fseek($fp, $pos);
                    while (!feof($fp)) {
                        $chunk = fgets($fp);
                        if ($chunk !== false) $output->writeln(rtrim($chunk, "\r\n"));
                    }
                    fclose($fp);
                }
                $pos = $size;
            }
            usleep(500000);
        }
    }
}

