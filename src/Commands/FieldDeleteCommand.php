<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use Totoglu\Console\Traits\InteractWithProcessWire;

final class FieldDeleteCommand extends Command
{
    use InteractWithProcessWire;

    protected function configure(): void
    {
        $this
            ->setName('field:delete')
            ->setDescription('Delete a field.')
            ->addArgument('name', InputArgument::OPTIONAL, 'The name of the field to delete');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name') ?? $this->searchField('Search for a field to delete');

        if ($name === 'No matching fields found') {
            return Command::SUCCESS;
        }

        $field = \ProcessWire\wire('fields')->get($name);

        if (!$field || !$field->id) {
            error("Field '{$name}' not found.");
            return Command::FAILURE;
        }

        if ($field->flags & \ProcessWire\Field::flagSystem) {
            error("Field '{$name}' is a system field and cannot be deleted.");
            return Command::FAILURE;
        }

        if (confirm("Are you sure you want to delete field '{$name}'? This will delete all data associated with it.", false)) {
            \ProcessWire\wire('fields')->delete($field);
            info("Field '{$name}' deleted.");
            return Command::SUCCESS;
        }

        return Command::SUCCESS;
    }
}
