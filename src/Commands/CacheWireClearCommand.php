<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class CacheWireClearCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('cache:wire:clear')
            ->setDescription('Clear WireCache by key or pattern.')
            ->addOption('key', null, InputOption::VALUE_REQUIRED, 'Exact cache key to delete')
            ->addOption('pattern', null, InputOption::VALUE_REQUIRED, 'SQL LIKE pattern for names (e.g., "Template%")')
            ->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $key = $input->getOption('key') ? (string)$input->getOption('key') : '';
        $pattern = $input->getOption('pattern') ? (string)$input->getOption('pattern') : '';
        $asJson = (bool)$input->getOption('json');

        if (!$key && !$pattern) {
            $io->error("Provide --key or --pattern.");
            return Command::FAILURE;
        }

        $deleted = 0;
        if ($key) {
            \ProcessWire\wire('cache')->delete($key);
            $deleted = 1;
        } else {
            $db = \ProcessWire\wire('database');
            $table = $db->quoteIdentifier('caches');
            $stmt = $db->prepare("DELETE FROM {$table} WHERE name LIKE :p");
            $stmt->bindValue(':p', $pattern);
            $stmt->execute();
            $deleted = $stmt->rowCount();
        }

        $data = ['deleted' => $deleted, 'key' => $key, 'pattern' => $pattern];
        if ($asJson) {
            $output->writeln(json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_SLASHES));
        } else {
            $io->success("Deleted {$deleted} cache entr" . ($deleted === 1 ? 'y' : 'ies') . ".");
        }
        return Command::SUCCESS;
    }
}

