<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

final class PageFindCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('page:find')
            ->setDescription('Find pages using a ProcessWire selector.')
            ->addArgument('selector', InputArgument::REQUIRED, 'The ProcessWire selector string')
            ->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $selector = $input->getArgument('selector');
        $pages = \ProcessWire\wire('pages')->find($selector);

        $asJson = (bool)$input->getOption('json');
        if ($asJson) {
            $items = [];
            foreach ($pages as $page) {
                $items[] = ['id' => $page->id, 'path' => $page->path, 'template' => $page->template->name];
            }
            $output->writeln(json_encode(['ok' => true, 'data' => ['items' => $items, 'count' => count($items)]], JSON_UNESCAPED_SLASHES));
        } else {
            $table = new Table($output);
            $table->setHeaders(['ID', 'Path', 'Template']);
            foreach ($pages as $page) {
                $table->addRow([
                    $page->id,
                    $page->path,
                    $page->template->name,
                ]);
            }
            $table->render();
            $output->writeln("\nFound " . count($pages) . " pages.");
        }

        return Command::SUCCESS;
    }
}
