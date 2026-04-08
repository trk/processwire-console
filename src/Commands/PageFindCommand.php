<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function Laravel\Prompts\text;
use function Laravel\Prompts\table;
use function Laravel\Prompts\info;
use function Laravel\Prompts\error;

final class PageFindCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('page:find')
            ->setDescription('Find pages using a ProcessWire selector.')
            ->addArgument('selector', InputArgument::OPTIONAL, 'The ProcessWire selector string')
            ->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $selector = $input->getArgument('selector');
        $asJson = (bool)$input->getOption('json');

        if (!$selector && $input->isInteractive() && !$asJson) {
            $selector = text('Selector string', required: true);
        }

        if (!$selector) {
            error("No selector provided.");
            return Command::FAILURE;
        }

        $pages = \ProcessWire\wire('pages')->find($selector);

        if ($asJson) {
            $items = [];
            foreach ($pages as $page) {
                $items[] = ['id' => $page->id, 'path' => $page->path, 'template' => $page->template->name];
            }
            $output->writeln(json_encode(['ok' => true, 'data' => ['items' => $items, 'count' => count($items)]], JSON_UNESCAPED_SLASHES));
        } else {
            $rows = [];
            foreach ($pages as $page) {
                $rows[] = [
                    $page->id,
                    $page->path,
                    $page->template->name,
                ];
            }
            if (empty($rows)) {
                info("No pages found matching '{$selector}'.");
            } else {
                table(
                    headers: ['ID', 'Path', 'Template'],
                    rows: $rows
                );
                info("Found " . count($pages) . " pages.");
            }
        }

        return Command::SUCCESS;
    }
}
