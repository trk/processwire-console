<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class TemplateDeleteCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('template:delete')
            ->setDescription('Delete a template.')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the template to delete');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getArgument('name');

        $template = \ProcessWire\wire('templates')->get($name);

        if (!$template || !$template->id) {
            $io->error("Template '{$name}' not found.");
            return Command::FAILURE;
        }

        if ($template->flags & \ProcessWire\Template::flagSystem) {
            $io->error("Template '{$name}' is a system template and cannot be deleted.");
            return Command::FAILURE;
        }

        if ($io->confirm("Are you sure you want to delete template '{$name}'? This will delete all pages using it.", false)) {
            try {
                \ProcessWire\wire('templates')->delete($template);
                $io->success("Template '{$name}' deleted.");
            } catch (\Exception $e) {
                $io->error("Error deleting template: " . $e->getMessage());
                return Command::FAILURE;
            }
            return Command::SUCCESS;
        }

        return Command::SUCCESS;
    }
}
