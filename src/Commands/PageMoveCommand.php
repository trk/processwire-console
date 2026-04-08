<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class PageMoveCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('page:move')
            ->setDescription('Move a page to a new parent.')
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Page ID')
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'Page path')
            ->addOption('parent', 'p', InputOption::VALUE_REQUIRED, 'New parent path or ID (required)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write changes')
            ->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $id = $input->getOption('id');
        $path = $input->getOption('path');
        $parentArg = (string)$input->getOption('parent');
        $dryRun = (bool)$input->getOption('dry-run');
        $asJson = (bool)$input->getOption('json');

        if ((!$id && !$path) || !$parentArg) {
            $io->error("Provide --id or --path and required --parent.");
            return Command::FAILURE;
        }

        $pages = \ProcessWire\wire('pages');
        $page = $id ? $pages->get((int)$id) : $pages->get((string)$path);
        if (!$page || !$page->id) {
            $io->error("Page not found.");
            return Command::FAILURE;
        }
        $newParent = is_numeric($parentArg) ? $pages->get((int)$parentArg) : $pages->get($parentArg);
        if (!$newParent || !$newParent->id) {
            $io->error("New parent not found: {$parentArg}");
            return Command::FAILURE;
        }

        $result = ['id' => $page->id, 'from' => $page->parent->path, 'to' => $newParent->path, 'dryRun' => $dryRun];
        if ($dryRun) {
            if ($asJson) {
                $output->writeln(json_encode(['ok' => true, 'data' => $result], JSON_UNESCAPED_SLASHES));
            } else {
                $io->note("Dry-run: would move page #{$page->id} from {$page->parent->path} to {$newParent->path}.");
            }
            return Command::SUCCESS;
        }

        $page->parent = $newParent;
        $page->save();

        if ($asJson) {
            $output->writeln(json_encode(['ok' => true, 'data' => $result + ['moved' => true]], JSON_UNESCAPED_SLASHES));
        } else {
            $io->success("Moved page #{$page->id} to {$newParent->path}.");
        }
        return Command::SUCCESS;
    }
}

