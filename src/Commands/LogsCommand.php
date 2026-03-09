<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class LogsCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('logs')
            ->setDescription('Retrieve ProcessWire logs.')
            ->addArgument('name', InputArgument::OPTIONAL, 'The name of the log (e.g. errors, messages)', 'errors')
            ->addArgument('limit', InputArgument::OPTIONAL, 'Number of entries to return', '10');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getArgument('name');
        $limit = (int) $input->getArgument('limit');

        $log = \ProcessWire\wire('log');
        $entries = $log->get($name, $limit);

        if (empty($entries)) {
            $io->note("No entries found in log: {$name}");
            return Command::SUCCESS;
        }

        foreach ($entries as $entry) {
            $output->writeln("[{$entry['date']}] {$entry['text']}");
        }

        return Command::SUCCESS;
    }
}
