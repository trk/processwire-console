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
use function Laravel\Prompts\text;
use Totoglu\Console\Traits\InteractWithProcessWire;

final class TemplateFieldsReorderCommand extends Command
{
    use InteractWithProcessWire;
    protected function configure(): void
    {
        $this
            ->setName('template:fields:reorder')
            ->setDescription('Reorder fields on a template (moves listed fields in given order to the front).')
            ->addOption('template', null, InputOption::VALUE_OPTIONAL, 'Template name')
            ->addOption('order', null, InputOption::VALUE_OPTIONAL, 'Comma-separated field names in desired order (front)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write changes')
            ->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $templateName = $input->getOption('template');
        $asJson = (bool)$input->getOption('json');
        
        if (!$templateName && !$asJson) {
            $templateName = $this->searchTemplate('Select the template to reorder fields');
            if ($templateName === 'No matching templates found') return Command::SUCCESS;
        }

        if (!$templateName) {
            error("Provide --template.");
            return Command::FAILURE;
        }

        $orderStr = $input->getOption('order') ? (string)$input->getOption('order') : '';
        if (!$orderStr && !$asJson) {
            $orderStr = text(
                label: 'Enter comma-separated field names in desired order',
                required: true
            );
        }

        $dryRun = (bool)$input->getOption('dry-run');

        if (!$orderStr) {
            error("Provide --order.");
            return Command::FAILURE;
        }
        
        $templates = \ProcessWire\wire('templates');
        $fields = \ProcessWire\wire('fields');
        $template = $templates->get($templateName);
        if (!$template || !$template->id) {
            error("Template not found: {$templateName}");
            return Command::FAILURE;
        }
        $fg = $template->fieldgroup;
        $names = array_map('trim', explode(',', $orderStr));

        // Validate fields exist and are attached (silently skip missing)
        $valid = [];
        foreach ($names as $n) {
            if ($n === '') continue;
            $f = $fg->getField($n);
            if ($f) $valid[] = $n;
        }

        $result = ['template' => $templateName, 'order' => $valid, 'dryRun' => $dryRun];
        if ($dryRun) {
            if ($asJson) {
                $output->writeln(json_encode(['ok' => true, 'data' => $result], JSON_UNESCAPED_SLASHES));
            } else {
                note("Dry-run: would move fields to front in order: " . implode(', ', $valid));
            }
            return Command::SUCCESS;
        }

        // Move each listed field to end then insert before current first to accumulate order at front
        foreach (array_reverse($valid) as $n) {
            $f = $fg->getField($n);
            if (!$f) continue;
            // Normalize: remove and add to end
            $fg->remove($f);
            $fg->add($f);
            // Move to front by inserting before first
            $first = $fg->first();
            if ($first && $first !== $f) {
                $fg->insertBefore($f, $first);
            }
        }

        if (method_exists($fg, 'save')) {
            $fg->save();
        } else {
            $template->save();
        }

        if ($asJson) {
            $output->writeln(json_encode(['ok' => true, 'data' => $result + ['reordered' => true]], JSON_UNESCAPED_SLASHES));
        } else {
            info("Reordered fields on template '{$templateName}'.");
        }
        return Command::SUCCESS;
    }
}

