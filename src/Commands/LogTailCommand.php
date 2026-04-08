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

final class LogTailCommand extends Command
{
    use InteractWithProcessWire;
    protected function configure(): void
    {
        $this
            ->setName('log:tail')
            ->setDescription('Tail a ProcessWire log file (like errors).')
            ->addArgument('name', InputArgument::OPTIONAL, 'The name of the log (e.g. errors, messages)', null)
            ->addOption('lines', null, InputOption::VALUE_REQUIRED, 'Number of lines to show from end', '200')
            ->addOption('follow', 'f', InputOption::VALUE_NONE, 'Follow appended output');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = (string) $input->getArgument('name');
        
        if (empty($name)) {
            $name = $this->searchLog('Select a log file');
            if ($name === 'No matching logs found') {
                error('No log files available.');
                return Command::FAILURE;
            }
        }

        $file = basename((string)$name);
        $lines = (int)$input->getOption('lines');
        $follow = (bool)$input->getOption('follow');

        $path = \ProcessWire\wire('config')->paths->logs . $file . '.txt';
        if (!is_file($path)) {
            error("Log file not found: {$path}");
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

