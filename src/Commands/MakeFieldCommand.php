<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class MakeFieldCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('make:field')
            ->setDescription('Create a new ProcessWire field.')
            ->addArgument('type', InputArgument::REQUIRED, 'The type of the field (e.g., text, textarea, checkbox)')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the field');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $type = $input->getArgument('type');
        $name = $input->getArgument('name');

        if (\ProcessWire\wire('fields')->get($name)->id) {
            $io->error("Field '{$name}' already exists.");
            return Command::FAILURE;
        }

        $pwType = "Fieldtype" . ucfirst($type);
        if (!\ProcessWire\wire('modules')->get($pwType)) {
            $io->error("Field type '{$pwType}' is not installed or doesn't exist.");
            return Command::FAILURE;
        }

        $f = new \ProcessWire\Field();
        $f->type = \ProcessWire\wire('modules')->get($pwType);
        $f->name = $name;
        $f->label = ucfirst($name);
        $f->save();

        $io->success("Field '{$name}' ({$type}) created successfully.");

        return Command::SUCCESS;
    }
}
