<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\text;

final class PermissionCreateCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('permission:create')
            ->setDescription('Create a new permission.')
            ->addArgument('name', InputArgument::OPTIONAL, 'The name of the permission')
            ->addArgument('title', InputArgument::OPTIONAL, 'The title of the permission');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $title = $input->getArgument('title');

        if (!$name && $input->isInteractive()) {
            $name = text('Permission name', required: true);
            if (!$title) {
                $title = text('Permission title (optional)', required: false);
            }
        }

        if (!$name) {
            error("Provide <name> argument or run interactively to enter it.");
            return Command::FAILURE;
        }

        $existing = \ProcessWire\wire('permissions')->get($name);
        if ($existing && $existing->id) {
            error("Permission '{$name}' already exists.");
            return Command::FAILURE;
        }

        $p = \ProcessWire\wire('permissions')->add($name);
        if ($title) $p->title = $title;
        $p->save();

        info("Permission '{$name}' created.");

        return Command::SUCCESS;
    }
}
