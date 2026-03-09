<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;

final class TemplateListCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('template:list')
            ->setDescription('List all templates in the ProcessWire installation.')
            ->addOption('search', 'q', InputOption::VALUE_REQUIRED, 'Search templates by name or tag')
            ->addOption('tag', 't', InputOption::VALUE_REQUIRED, 'Filter by template tag')
            ->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $search = $input->getOption('search');
        $tag = $input->getOption('tag');

        $templates = \ProcessWire\wire('templates');
        $filtered = [];

        foreach ($templates as $template) {
            // Apply filtering
            if ($tag && stripos($template->tags, $tag) === false) continue;
            if ($search && (stripos($template->name, $search) === false && stripos($template->tags, $search) === false)) continue;

            $filtered[] = $template;
        }

        if (empty($filtered)) {
            $io->warning("No templates found matching criteria.");
            return Command::SUCCESS;
        }

        $asJson = (bool)$input->getOption('json');
        if ($asJson) {
            $items = [];
            foreach ($filtered as $template) {
                $flags = [];
                if ($template->flags & \ProcessWire\Template::flagSystem) $flags[] = 'sys';
                $fieldCount = count($template->fields ?: []);
                $pageCount = \ProcessWire\wire('pages')->count("template={$template->name}, include=all");
                $items[] = [
                    'id' => $template->id,
                    'name' => $template->name,
                    'tag' => $template->tags ?: '',
                    'fields' => $fieldCount,
                    'pages' => (int)$pageCount,
                    'flags' => $flags,
                ];
            }
            $output->writeln(json_encode(['ok' => true, 'data' => ['items' => $items, 'total' => count($filtered)]], JSON_UNESCAPED_SLASHES));
        } else {
            $table = new Table($output);
            $table->setHeaders(['ID', 'Name', 'Tag', 'Fields', 'Pages', 'Flags']);
            foreach ($filtered as $template) {
                $flags = [];
                if ($template->flags & \ProcessWire\Template::flagSystem) $flags[] = '<fg=red>sys</>';
                $fieldCount = count($template->fields ?: []);
                $pageCount = \ProcessWire\wire('pages')->count("template={$template->name}, include=all");
                $table->addRow([
                    $template->id,
                    $template->name,
                    $template->tags ?: '-',
                    $fieldCount,
                    $pageCount,
                    implode('|', $flags) ?: '-'
                ]);
            }
            $table->render();
            $io->note("Total templates: " . count($filtered));
        }

        return Command::SUCCESS;
    }
}
