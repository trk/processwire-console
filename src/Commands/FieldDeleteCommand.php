<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class FieldDeleteCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('field:delete')
            ->setDescription('Delete a field.')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the field to delete');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getArgument('name');

        $field = \ProcessWire\wire('fields')->get($name);

        if (!$field || !$field->id) {
            $io->error("Field '{$name}' not found.");
            return Command::FAILURE;
        }

        if ($field->flags & \ProcessWire\Field::flagSystem) {
            $io->error("Field '{$name}' is a system field and cannot be deleted.");
            return Command::FAILURE;
        }

        if ($io->confirm("Are you sure you want to delete field '{$name}'? This will delete all data associated with it.", false)) {
            \ProcessWire\wire('fields')->delete($field);
            $io->success("Field '{$name}' deleted.");
            return Command::SUCCESS;
        }

        return Command::SUCCESS;
    }
}
