<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class FieldRenameCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('field:rename')
            ->setDescription('Rename a field.')
            ->addOption('old', null, InputOption::VALUE_REQUIRED, 'Old field name')
            ->addOption('new', null, InputOption::VALUE_REQUIRED, 'New field name')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write changes')
            ->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $old = (string)$input->getOption('old');
        $new = (string)$input->getOption('new');
        $dryRun = (bool)$input->getOption('dry-run');
        $asJson = (bool)$input->getOption('json');

        if (!$old || !$new) {
            $io->error("Provide --old and --new.");
            return Command::FAILURE;
        }
        $fields = \ProcessWire\wire('fields');
        $f = $fields->get($old);
        if (!$f || !$f->id) {
            $io->error("Field not found: {$old}");
            return Command::FAILURE;
        }
        $existingNew = $fields->get($new);
        if ($existingNew && $existingNew->id) {
            $io->error("A field named '{$new}' already exists.");
            return Command::FAILURE;
        }

        $result = ['old' => $old, 'new' => $new, 'dryRun' => $dryRun];
        if ($dryRun) {
            if ($asJson) {
                $output->writeln(json_encode(['ok' => true, 'data' => $result], JSON_UNESCAPED_SLASHES));
            } else {
                $io->note("Dry-run: would rename field '{$old}' to '{$new}'.");
            }
            return Command::SUCCESS;
        }

        $f->name = $new;
        $f->save();

        if ($asJson) {
            $output->writeln(json_encode(['ok' => true, 'data' => $result + ['renamed' => true]], JSON_UNESCAPED_SLASHES));
        } else {
            $io->success("Renamed field '{$old}' to '{$new}'.");
        }
        return Command::SUCCESS;
    }
}

