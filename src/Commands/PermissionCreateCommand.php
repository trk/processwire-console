<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class PermissionCreateCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('permission:create')
            ->setDescription('Create a new permission.')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the permission')
            ->addArgument('title', InputArgument::OPTIONAL, 'The title of the permission');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getArgument('name');
        $title = $input->getArgument('title');

        $existing = \ProcessWire\wire('permissions')->get($name);
        if ($existing && $existing->id) {
            $io->error("Permission '{$name}' already exists.");
            return Command::FAILURE;
        }

        $p = \ProcessWire\wire('permissions')->add($name);
        if ($title) $p->title = $title;
        $p->save();

        $io->success("Permission '{$name}' created.");

        return Command::SUCCESS;
    }
}
