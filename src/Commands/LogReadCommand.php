<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Traits\InteractWithProcessWire;

use function Laravel\Prompts\table;
use function Laravel\Prompts\error;
use function Laravel\Prompts\note;
use function ProcessWire\wire;

final class LogReadCommand extends Command
{
    use InteractWithProcessWire;
    protected function configure(): void
    {
        $this
            ->setName('log:read')
            ->setDescription('Retrieve ProcessWire logs.')
            ->addArgument('name', InputArgument::OPTIONAL, 'The name of the log (e.g. errors, messages)', null)
            ->addArgument('limit', InputArgument::OPTIONAL, 'Number of entries to return', '10')
            ->addOption('sort', null, InputOption::VALUE_REQUIRED, 'Sort direction (asc or desc)', 'desc');
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

        $sanitizer = wire('sanitizer');
        $name = preg_replace('/\.txt$/i', '', basename($name));
        $name = $sanitizer->name((string) $name);

        $limit = (int) $input->getArgument('limit');
        if ($limit < 1) {
            $limit = 10;
        }
        if ($limit > 5000) {
            $limit = 5000;
        }
        $sort = strtolower((string) $input->getOption('sort'));

        $log = wire('log');
        $entries = $log->getEntries($name, ['limit' => $limit]);

        if (empty($entries)) {
            note("No entries found in log: {$name}");
            return Command::SUCCESS;
        }

        if ($sort === 'desc') {
            $entries = array_reverse($entries);
        }

        $rows = [];
        foreach ($entries as $entry) {
            $rows[] = [
                $entry['date'] ?? '-',
                $entry['user'] ?? '-',
                $entry['url'] ?? '-',
                wordwrap((string)($entry['text'] ?? '-'), 80, "\n", true)
            ];
        }

        table(['Date', 'User', 'URL', 'Text'], $rows);

        return Command::SUCCESS;
    }
}
