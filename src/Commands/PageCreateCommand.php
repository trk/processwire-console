<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use function Laravel\Prompts\text;
use function Laravel\Prompts\select;
use function Laravel\Prompts\confirm;

final class PageCreateCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('page:create')
            ->setDescription('Create a new page under a parent with a given template.')
            ->addOption('parent', 'p', InputOption::VALUE_REQUIRED, 'Parent page path or ID (required)')
            ->addOption('template', 't', InputOption::VALUE_REQUIRED, 'Template name (required)')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Page name (optional, derived from title if omitted)')
            ->addOption('title', null, InputOption::VALUE_REQUIRED, 'Page title (optional)')
            ->addOption('unpublished', null, InputOption::VALUE_NONE, 'Create as unpublished')
            ->addOption('interactive', 'i', InputOption::VALUE_NONE, 'Prompt for missing values and field inputs')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write changes, only show what would happen')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output machine-readable JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $parentArg = (string)$input->getOption('parent');
        $templateName = (string)$input->getOption('template');
        $name = $input->getOption('name') ? (string)$input->getOption('name') : null;
        $title = $input->getOption('title') ? (string)$input->getOption('title') : null;
        $unpublished = (bool)$input->getOption('unpublished');
        $wantInteractive = (bool)$input->getOption('interactive');
        $dryRun = (bool)$input->getOption('dry-run');
        $asJson = (bool)$input->getOption('json');

        $pages = \ProcessWire\wire('pages');
        $templates = \ProcessWire\wire('templates');
        $sanitizer = \ProcessWire\wire('sanitizer');

        if (($wantInteractive || !$parentArg || !$templateName) && $input->isInteractive() && !$asJson) {
            if (!$templateName) {
                $templateNames = [];
                foreach ($templates as $t) $templateNames[] = $t->name;
                $templateName = select('Template', $templateNames);
            }
            if (!$parentArg) {
                $parentArg = text('Parent (path or ID)');
            }
            if (!$title) {
                $title = text('Title', required: false);
            }
            if (!$name) {
                $defaultName = $title ? $sanitizer->pageName($title, true) : null;
                $name = text('Name', default: $defaultName ?? '', required: false);
                if ($name === '' && $defaultName) $name = $defaultName;
            }
        }

        $parent = is_numeric($parentArg) ? $pages->get((int)$parentArg) : $pages->get($parentArg);
        if (!$parent || !$parent->id) {
            $io->error("Parent not found: {$parentArg}");
            return Command::FAILURE;
        }

        $template = $templates->get($templateName);
        if (!$template || !$template->id) {
            $io->error("Template not found: {$templateName}");
            return Command::FAILURE;
        }

        if (!$name && $title) {
            $name = $sanitizer->pageName($title, true);
        }

        if (!$name) {
            $name = 'page-' . date('Ymd-His');
        }

        $result = [
            'parent' => $parent->path,
            'template' => $templateName,
            'name' => $name,
            'title' => $title ?? '',
            'status' => $unpublished ? 'unpublished' : 'published',
            'dryRun' => $dryRun,
        ];

        if ($dryRun) {
            if ($asJson) {
                $output->writeln(json_encode(['ok' => true, 'data' => $result], JSON_UNESCAPED_SLASHES));
            } else {
                $io->note("Dry-run: would create page '{$name}' under '{$parent->path}' with template '{$templateName}'.");
            }
            return Command::SUCCESS;
        }

        $page = new \ProcessWire\Page();
        $page->template = $template;
        $page->parent = $parent;
        $page->name = $name;
        if ($title) {
            $page->title = $title;
        }
        if ($unpublished) {
            $page->addStatus(\ProcessWire\Page::statusUnpublished);
        }
        if (($wantInteractive || (!$input->getOption('title') && !$input->getOption('name'))) && $input->isInteractive() && !$asJson) {
            $fill = confirm('Fill template fields now?', default: false);
            if ($fill) {
                foreach ($template->fieldgroup as $f) {
                    $fname = $f->name;
                    if ($fname === 'title') continue;
                    $ftype = $f->type;
                    $label = $f->label ?: $fname;
                    $desc = $f->description ?: '';
                    $prompt = trim($label . ($desc ? " — {$desc}" : ''));
                    $val = null;
                    if ($ftype instanceof \ProcessWire\FieldtypeText) {
                        $val = text($prompt, required: false);
                        $val = $sanitizer->text($val);
                    } elseif ($ftype instanceof \ProcessWire\FieldtypeTextarea) {
                        $val = text($prompt, required: false);
                        $val = $sanitizer->textarea($val);
                    }
                    if ($val !== null && $val !== '') {
                        $page->set($fname, $val);
                    }
                }
            }
        }
        $page->save();

        $result['id'] = $page->id;
        $result['url'] = $page->url;

        if ($asJson) {
            $output->writeln(json_encode(['ok' => true, 'data' => $result], JSON_UNESCAPED_SLASHES));
        } else {
            $io->success("Created page #{$page->id} at {$page->url} (template: {$templateName})");
        }

        return Command::SUCCESS;
    }
}
