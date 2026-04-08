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

final class PagePublishCommand extends Command
{
    use InteractWithProcessWire;

    protected function configure(): void
    {
        $this
            ->setName('page:publish')
            ->setDescription('Publish a page (remove unpublished status).')
            ->addOption('id', null, InputOption::VALUE_OPTIONAL, 'Page ID')
            ->addOption('path', null, InputOption::VALUE_OPTIONAL, 'Page path')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write changes')
            ->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getOption('id');
        $path = $input->getOption('path');
        $dryRun = (bool)$input->getOption('dry-run');
        $asJson = (bool)$input->getOption('json');

        if (!$id && !$path && !$asJson) {
            $id = $this->searchPage('Select a page to publish');
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

        $result = ['id' => $page->id, 'path' => $page->path, 'wasUnpublished' => $page->isUnpublished(), 'dryRun' => $dryRun];

        if ($dryRun) {
            if ($asJson) {
                $output->writeln(json_encode(['ok' => true, 'data' => $result], JSON_UNESCAPED_SLASHES));
            } else {
                note("Dry-run: would publish page #{$page->id} ({$page->path}).");
            }
            return Command::SUCCESS;
        }

        $page->removeStatus(\ProcessWire\Page::statusUnpublished);
        $page->save();

        if ($asJson) {
            $output->writeln(json_encode(['ok' => true, 'data' => $result + ['published' => true]], JSON_UNESCAPED_SLASHES));
        } else {
            info("Published page #{$page->id} ({$page->path}).");
        }
        return Command::SUCCESS;
    }
}

