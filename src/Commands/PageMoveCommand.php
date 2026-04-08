<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use Totoglu\Console\Traits\InteractWithProcessWire;

final class PageMoveCommand extends Command
{
    use InteractWithProcessWire;

    protected function configure(): void
    {
        $this
            ->setName('page:move')
            ->setDescription('Move a page to a new parent.')
            ->addOption('id', null, InputOption::VALUE_OPTIONAL, 'Page ID')
            ->addOption('path', null, InputOption::VALUE_OPTIONAL, 'Page path')
            ->addOption('parent', 'p', InputOption::VALUE_OPTIONAL, 'New parent path or ID')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write changes')
            ->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getOption('id');
        $path = $input->getOption('path');
        $parentArg = (string)$input->getOption('parent');
        $dryRun = (bool)$input->getOption('dry-run');
        $asJson = (bool)$input->getOption('json');

        if (!$id && !$path && !$asJson) {
            $id = $this->searchPage('Select a page to move');
            if ($id === '' || str_starts_with((string)$id, 'No matching')) return Command::SUCCESS;
        }

        if (!$parentArg && !$asJson) {
            $parentArg = (string)$this->searchPage('Select the new parent page');
        }

        if ((!$id && !$path) || !$parentArg) {
            error("Provide --id or --path and --parent.");
            return Command::FAILURE;
        }

        $pages = \ProcessWire\wire('pages');
        $page = $id ? $pages->get((int)$id) : $pages->get((string)$path);
        if (!$page || !$page->id) {
            error("Page not found.");
            return Command::FAILURE;
        }
        $newParent = is_numeric($parentArg) ? $pages->get((int)$parentArg) : $pages->get($parentArg);
        if (!$newParent || !$newParent->id) {
            error("New parent not found: {$parentArg}");
            return Command::FAILURE;
        }

        $result = ['id' => $page->id, 'from' => $page->parent->path, 'to' => $newParent->path, 'dryRun' => $dryRun];
        if ($dryRun) {
            if ($asJson) {
                $output->writeln(json_encode(['ok' => true, 'data' => $result], JSON_UNESCAPED_SLASHES));
            } else {
                note("Dry-run: would move page #{$page->id} from {$page->parent->path} to {$newParent->path}.");
            }
            return Command::SUCCESS;
        }

        $page->parent = $newParent;
        $page->save();

        if ($asJson) {
            $output->writeln(json_encode(['ok' => true, 'data' => $result + ['moved' => true]], JSON_UNESCAPED_SLASHES));
        } else {
            info("Moved page #{$page->id} to {$newParent->path}.");
        }
        return Command::SUCCESS;
    }
}

