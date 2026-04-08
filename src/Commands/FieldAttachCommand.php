<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class FieldAttachCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('field:attach')
            ->setDescription('Attach a field to a template with optional position.')
            ->addOption('field', null, InputOption::VALUE_REQUIRED, 'Field name (required)')
            ->addOption('template', null, InputOption::VALUE_REQUIRED, 'Template name (required)')
            ->addOption('position', null, InputOption::VALUE_REQUIRED, 'Position: first|last|after=FIELD|before=FIELD')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write changes')
            ->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $fieldName = (string)$input->getOption('field');
        $templateName = (string)$input->getOption('template');
        $position = $input->getOption('position') ? (string)$input->getOption('position') : 'last';
        $dryRun = (bool)$input->getOption('dry-run');
        $asJson = (bool)$input->getOption('json');

        if (!$fieldName || !$templateName) {
            $io->error("Provide --field and --template.");
            return Command::FAILURE;
        }

        $fields = \ProcessWire\wire('fields');
        $templates = \ProcessWire\wire('templates');
        $field = $fields->get($fieldName);
        $template = $templates->get($templateName);
        if (!$field || !$field->id) {
            $io->error("Field not found: {$fieldName}");
            return Command::FAILURE;
        }
        if (!$template || !$template->id) {
            $io->error("Template not found: {$templateName}");
            return Command::FAILURE;
        }

        $fg = $template->fieldgroup;
        $exists = (bool)$fg->getField($fieldName);

        $result = ['field' => $fieldName, 'template' => $templateName, 'position' => $position, 'alreadyAttached' => $exists, 'dryRun' => $dryRun];
        if ($dryRun) {
            if ($asJson) {
                $output->writeln(json_encode(['ok' => true, 'data' => $result], JSON_UNESCAPED_SLASHES));
            } else {
                $io->note("Dry-run: would attach field '{$fieldName}' to template '{$templateName}' at '{$position}'.");
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
            $io->success("Attached field '{$fieldName}' to template '{$templateName}'.");
        }
        return Command::SUCCESS;
    }
}

