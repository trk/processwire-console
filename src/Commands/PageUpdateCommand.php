<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function Laravel\Prompts\text;
use function Laravel\Prompts\select;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\warning;
use Totoglu\Console\Traits\InteractWithProcessWire;

final class PageUpdateCommand extends Command
{
    use InteractWithProcessWire;

    protected function configure(): void
    {
        $this
            ->setName('page:update')
            ->setDescription('Update fields and/or status on a page.')
            ->addOption('id', null, InputOption::VALUE_OPTIONAL, 'Page ID')
            ->addOption('path', null, InputOption::VALUE_OPTIONAL, 'Page path')
            ->addOption('set', null, InputOption::VALUE_OPTIONAL, 'Comma-separated key=value pairs (e.g., "title=Hello,headline=Hi")')
            ->addOption('status', null, InputOption::VALUE_OPTIONAL, 'Optional status: publish|unpublish|hide|show|lock|unlock')
            ->addOption('interactive', 'i', InputOption::VALUE_NONE, 'Prompt for page and field values')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write changes')
            ->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getOption('id');
        $path = $input->getOption('path');
        $set = $input->getOption('set') ? (string)$input->getOption('set') : '';
        $statusOp = $input->getOption('status') ? (string)$input->getOption('status') : '';
        $wantInteractive = (bool)$input->getOption('interactive');
        $dryRun = (bool)$input->getOption('dry-run');
        $asJson = (bool)$input->getOption('json');

        if (($wantInteractive && $input->isInteractive() && !$asJson) && (!$id && !$path)) {
            $path = (string)$this->searchPage('Select page to update');
            if ($path === '' || str_starts_with($path, 'No matching')) return Command::SUCCESS;
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

        if ($wantInteractive && $input->isInteractive() && !$asJson) {
            $statusChoices = ['none','publish','unpublish','hide','show','lock','unlock'];
            if (!$statusOp) {
                $sel = select('Status change', $statusChoices, default: 'none');
                $statusOp = $sel !== 'none' ? $sel : '';
            }
            $fill = confirm('Edit template fields now?', default: false);
            if ($fill) {
                $sanitizer = \ProcessWire\wire('sanitizer');
                foreach ($page->template->fieldgroup as $f) {
                    $fname = $f->name;
                    $ftype = $f->type;
                    $label = $f->label ?: $fname;
                    $desc = $f->description ?: '';
                    $prompt = trim($label . ($desc ? " — {$desc}" : ''));
                    $current = (string)$page->get($fname);
                    $val = null;
                    if ($ftype instanceof \ProcessWire\FieldtypeText) {
                        $val = text($prompt, default: $current, required: false);
                        $val = $sanitizer->text($val);
                    } elseif ($ftype instanceof \ProcessWire\FieldtypeTextarea) {
                        $val = text($prompt, default: $current, required: false);
                        $val = $sanitizer->textarea($val);
                    }
                    if ($val !== null && $val !== $current) {
                        $set .= ($set ? ',' : '') . "{$fname}={$val}";
                    }
                }
            }
        }

        $changes = [];
        if ($set) {
            foreach (explode(',', $set) as $pair) {
                $pair = trim($pair);
                if ($pair === '') continue;
                $parts = explode('=', $pair, 2);
                if (count($parts) !== 2) continue;
                $k = trim($parts[0]);
                $v = trim($parts[1]);
                $changes[$k] = $v;
            }
        }

        $result = ['id' => $page->id, 'path' => $page->path, 'changes' => $changes, 'status' => $statusOp, 'dryRun' => $dryRun];
        if ($dryRun) {
            if ($asJson) {
                $output->writeln(json_encode(['ok' => true, 'data' => $result], JSON_UNESCAPED_SLASHES));
            } else {
                note("Dry-run: would update page #{$page->id} with: " . json_encode($changes) . " and status '{$statusOp}'.");
            }
            return Command::SUCCESS;
        }

        foreach ($changes as $k => $v) {
            try {
                $page->set($k, $v);
            } catch (\Throwable $e) {
                warning("Field '{$k}': " . $e->getMessage());
            }
        }

        if ($statusOp) {
            switch ($statusOp) {
                case 'publish':
                    $page->removeStatus(\ProcessWire\Page::statusUnpublished);
                    break;
                case 'unpublish':
                    $page->addStatus(\ProcessWire\Page::statusUnpublished);
                    break;
                case 'hide':
                    $page->addStatus(\ProcessWire\Page::statusHidden);
                    break;
                case 'show':
                    $page->removeStatus(\ProcessWire\Page::statusHidden);
                    break;
                case 'lock':
                    $page->addStatus(\ProcessWire\Page::statusLocked);
                    break;
                case 'unlock':
                    $page->removeStatus(\ProcessWire\Page::statusLocked);
                    break;
            }
        }

        $page->save();

        if ($asJson) {
            $output->writeln(json_encode(['ok' => true, 'data' => $result + ['saved' => true]], JSON_UNESCAPED_SLASHES));
        } else {
            info("Updated page #{$page->id}.");
        }
        return Command::SUCCESS;
    }
}
