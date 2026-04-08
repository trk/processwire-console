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
use function Laravel\Prompts\warning;
use function Laravel\Prompts\confirm;
use Totoglu\Console\Traits\InteractWithProcessWire;

final class FieldDetachCommand extends Command
{
    use InteractWithProcessWire;

    protected function configure(): void
    {
        $this
            ->setName('field:detach')
            ->setDescription('Detach a field from a template.')
            ->addOption('field', null, InputOption::VALUE_OPTIONAL, 'Field name')
            ->addOption('template', null, InputOption::VALUE_OPTIONAL, 'Template name')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write changes')
            ->addOption('json', null, InputOption::VALUE_NONE, 'JSON output')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip interactive confirmations');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $fieldName = $input->getOption('field');
        $asJson = (bool)$input->getOption('json');
        
        if (!$fieldName && !$asJson) {
            $fieldName = $this->searchField('Select the field to detach');
            if ($fieldName === 'No matching fields found') return Command::SUCCESS;
        }

        $templateName = $input->getOption('template');
        if (!$templateName && !$asJson) {
            $templateName = $this->searchTemplate('Select the template to detach from');
            if ($templateName === 'No matching templates found') return Command::SUCCESS;
        }

        $dryRun = (bool)$input->getOption('dry-run');
        $force = (bool)$input->getOption('force');

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
        $attached = (bool)$fg->getField($fieldName);
        if (!$attached) {
            warning("Field '{$fieldName}' is not attached to template '{$templateName}'.");
            return Command::SUCCESS;
        }

        if (!$force && !$asJson && !$dryRun) {
            if (!confirm("Detach field '{$fieldName}' from template '{$templateName}'?", default: false)) {
                note("Aborted.");
                return Command::SUCCESS;
            }
        }

        $result = ['field' => $fieldName, 'template' => $templateName, 'dryRun' => $dryRun];
        if ($dryRun) {
            if ($asJson) {
                $output->writeln(json_encode(['ok' => true, 'data' => $result], JSON_UNESCAPED_SLASHES));
            } else {
                note("Dry-run: would detach field '{$fieldName}' from template '{$templateName}'.");
            }
            return Command::SUCCESS;
        }

        $fg->remove($field);
        if (method_exists($fg, 'save')) {
            $fg->save();
        } else {
            $template->save();
        }

        if ($asJson) {
            $output->writeln(json_encode(['ok' => true, 'data' => $result + ['detached' => true]], JSON_UNESCAPED_SLASHES));
        } else {
            info("Detached field '{$fieldName}' from template '{$templateName}'.");
        }
        return Command::SUCCESS;
    }
}

