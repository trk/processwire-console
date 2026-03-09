<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;

final class PageListCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('page:list')
            ->setDescription('List pages in the ProcessWire installation with advanced filtering.')
            ->addOption('template', 't', InputOption::VALUE_REQUIRED, 'Filter by template name')
            ->addOption('parent', 'p', InputOption::VALUE_REQUIRED, 'Filter by parent path or ID')
            ->addOption('sort', 's', InputOption::VALUE_REQUIRED, 'Sort field (e.g., -created, title)', '-created')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Number of pages to show', '20')
            ->addOption('include', 'i', InputOption::VALUE_REQUIRED, 'Include hidden/all/unpublished', 'all')
            ->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $template = $input->getOption('template');
        $parent = $input->getOption('parent');
        $sort = $input->getOption('sort');
        $limit = $input->getOption('limit');
        $include = $input->getOption('include');

        $selectorParts = [];
        if ($template) $selectorParts[] = "template={$template}";
        if ($parent) $selectorParts[] = "parent={$parent}";
        if ($sort) $selectorParts[] = "sort={$sort}";
        if ($limit) $selectorParts[] = "limit={$limit}";
        if ($include) $selectorParts[] = "include={$include}";

        $selector = implode(', ', $selectorParts);

        $pages = \ProcessWire\wire('pages')->find($selector ?: "limit=20, sort=-created");
        $asJson = (bool)$input->getOption('json');

        if (!$pages->count()) {
            $io->warning("No pages found matching selector: [{$selector}]");
            return Command::SUCCESS;
        }

        if ($asJson) {
            $items = [];
            foreach ($pages as $page) {
                $status = [];
                if ($page->isHidden()) $status[] = 'hidden';
                if ($page->isUnpublished()) $status[] = 'unpub';
                if ($page->isLocked()) $status[] = 'locked';
                $items[] = [
                    'id' => $page->id,
                    'path' => $page->path,
                    'title' => (string)($page->title ?: $page->name),
                    'template' => $page->template->name,
                    'status' => $status ?: ['pub'],
                    'modified' => (int)$page->modified,
                ];
            }
            $output->writeln(json_encode(['ok' => true, 'data' => ['items' => $items, 'total' => $pages->getTotal(), 'selector' => $selector ?: 'standard']], JSON_UNESCAPED_SLASHES));
        } else {
            $table = new Table($output);
            $table->setHeaders(['ID', 'Path', 'Title', 'Template', 'Status', 'Modified']);
            foreach ($pages as $page) {
                $status = [];
                if ($page->isHidden()) $status[] = '<fg=yellow>hidden</>';
                if ($page->isUnpublished()) $status[] = '<fg=red>unpub</>';
                if ($page->isLocked()) $status[] = '<fg=blue>locked</>';
                $table->addRow([
                    $page->id,
                    $page->path,
                    mb_strimwidth($page->title ?: $page->name, 0, 30, '...'),
                    $page->template->name,
                    implode('|', $status) ?: 'pub',
                    date('Y-m-d H:i', (int)$page->modified)
                ]);
            }
            $table->render();
            $io->note("Found " . $pages->getTotal() . " total pages for selector: [" . ($selector ?: "standard") . "]");
        }

        return Command::SUCCESS;
    }
}
