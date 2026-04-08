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
use function Laravel\Prompts\confirm;
use Totoglu\Console\Traits\InteractWithProcessWire;

final class PageTrashCommand extends Command
{
    use InteractWithProcessWire;

    protected function configure(): void
    {
        $this
            ->setName('page:trash')
            ->setDescription('Move a page to Trash.')
            ->addOption('id', null, InputOption::VALUE_OPTIONAL, 'Page ID')
            ->addOption('path', null, InputOption::VALUE_OPTIONAL, 'Page path')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write changes')
            ->addOption('json', null, InputOption::VALUE_NONE, 'JSON output')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip interactive confirmations');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getOption('id');
        $path = $input->getOption('path');
        $dryRun = (bool)$input->getOption('dry-run');
        $asJson = (bool)$input->getOption('json');
        $force = (bool)$input->getOption('force');

        if (!$id && !$path && !$asJson) {
            $id = $this->searchPage('Select a page to move to Trash');
            if ($id === '' || str_starts_with((string)$id, 'No matching')) return Command::SUCCESS;
        }

        if (!$id && !$path) {
            error("Provide --id or --path.");
            return Command::FAILURE;
        }

        $pages = \ProcessWire\wire('pages');
        $page = $id ? $pages->get((int)$id) : $pages->get((string)$path);
        if (!$page || !$page->id) {
            error("Page not found.");
            return Command::FAILURE;
        }

        if (!$force && !$asJson && !$dryRun) {
            if (!confirm("Move page #{$page->id} ({$page->path}) to Trash?", false)) {
                note("Aborted.");
                return Command::SUCCESS;
            }
        }

        if ($dryRun) {
            if ($asJson) {
                $output->writeln(json_encode(['ok' => true, 'data' => ['id' => $page->id, 'path' => $page->path, 'dryRun' => true]], JSON_UNESCAPED_SLASHES));
            } else {
                note("Dry-run: would trash page #{$page->id} ({$page->path}).");
            }
            return Command::SUCCESS;
        }

        $pages->trash($page, true);

        if ($asJson) {
            $output->writeln(json_encode(['ok' => true, 'data' => ['id' => $page->id, 'trashed' => true]], JSON_UNESCAPED_SLASHES));
        } else {
            info("Trashed page #{$page->id} ({$page->path}).");
        }
        return Command::SUCCESS;
    }
}

