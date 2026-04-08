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
use function Laravel\Prompts\select;
use Totoglu\Console\Traits\InteractWithProcessWire;

final class FieldAttachCommand extends Command
{
    use InteractWithProcessWire;

    protected function configure(): void
    {
        $this
            ->setName('field:attach')
            ->setDescription('Attach a field to a template with optional position.')
            ->addOption('field', null, InputOption::VALUE_OPTIONAL, 'Field name')
            ->addOption('template', null, InputOption::VALUE_OPTIONAL, 'Template name')
            ->addOption('position', null, InputOption::VALUE_OPTIONAL, 'Position: first|last|after=FIELD|before=FIELD')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write changes')
            ->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $fieldName = $input->getOption('field');
        $asJson = (bool)$input->getOption('json');

        if (!$fieldName && !$asJson) {
            $fieldName = $this->searchField('Select the field to attach');
            if ($fieldName === 'No matching fields found') return Command::SUCCESS;
        }

        $templateName = $input->getOption('template');
        if (!$templateName && !$asJson) {
            $templateName = $this->searchTemplate('Select the template to attach to');
            if ($templateName === 'No matching templates found') return Command::SUCCESS;
        }

        $position = $input->getOption('position');
        if (!$position && !$asJson) {
            $position = select(
                label: 'Where should this field be placed?',
                options: ['last' => 'At the end', 'first' => 'At the beginning'],
                default: 'last'
            );
        } elseif (!$position) {
            $position = 'last';
        }

        $dryRun = (bool)$input->getOption('dry-run');

        if (!$fieldName || !$templateName) {
            error("Provide --field and --template.");
            return Command::FAILURE;
        }

        $fields = \ProcessWire\wire('fields');
        $templates = \ProcessWire\wire('templates');
        $field = $fields->get($fieldName);
        $template = $templates->get($templateName);
        if (!$field || !$field->id) {
            error("Field not found: {$fieldName}");
            return Command::FAILURE;
        }
        if (!$template || !$template->id) {
            error("Template not found: {$templateName}");
            return Command::FAILURE;
        }

        $fg = $template->fieldgroup;
        $exists = (bool)$fg->getField($fieldName);

        $result = ['field' => $fieldName, 'template' => $templateName, 'position' => $position, 'alreadyAttached' => $exists, 'dryRun' => $dryRun];
        if ($dryRun) {
            if ($asJson) {
                $output->writeln(json_encode(['ok' => true, 'data' => $result], JSON_UNESCAPED_SLASHES));
            } else {
                note("Dry-run: would attach field '{$fieldName}' to template '{$templateName}' at '{$position}'.");
            }
            return Command::SUCCESS;
        }

        if (!$exists) {
            $fg->add($field);
        }

        // Positioning
        if (str_starts_with($position, 'after=')) {
            $afterName = substr($position, 6);
            $afterField = $fg->getField($afterName);
            if ($afterField) $fg->insertAfter($field, $afterField);
        } elseif (str_starts_with($position, 'before=')) {
            $beforeName = substr($position, 7);
            $beforeField = $fg->getField($beforeName);
            if ($beforeField) $fg->insertBefore($field, $beforeField);
        } elseif ($position === 'first') {
            $first = $fg->first();
            if ($first) $fg->insertBefore($field, $first);
        } // last is default via add()

        // Save fieldgroup or template
        if (method_exists($fg, 'save')) {
            $fg->save();
        } else {
            $template->save();
        }

        if ($asJson) {
            $output->writeln(json_encode(['ok' => true, 'data' => $result + ['attached' => true]], JSON_UNESCAPED_SLASHES));
        } else {
            info("Attached field '{$fieldName}' to template '{$templateName}'.");
        }
        return Command::SUCCESS;
    }
}

