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

final class TemplateRenameCommand extends Command
{
    use InteractWithProcessWire;
    protected function configure(): void
    {
        $this
            ->setName('template:rename')
            ->setDescription('Rename a template.')
            ->addOption('old', null, InputOption::VALUE_OPTIONAL, 'Old template name')
            ->addOption('new', null, InputOption::VALUE_OPTIONAL, 'New template name')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write changes')
            ->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $old = $input->getOption('old');
        $asJson = (bool)$input->getOption('json');
        
        if (!$old && !$asJson) {
            $old = $this->searchTemplate('Select the template to rename');
            if ($old === 'No matching templates found') return Command::SUCCESS;
        }

        $new = $input->getOption('new');
        if (!$new && !$asJson) {
            $new = text(
                label: 'Enter the new template name',
                required: true,
                validate: fn($value) => preg_match('/^[a-z_][a-z0-9_]*$/', $value) ? null : 'Invalid template name format'
            );
        }

        $dryRun = (bool)$input->getOption('dry-run');

        if (!$old || !$new) {
            error("Provide --old and --new.");
            return Command::FAILURE;
        }
        $templates = \ProcessWire\wire('templates');
        $t = $templates->get($old);
        if (!$t || !$t->id) {
            error("Template not found: {$old}");
            return Command::FAILURE;
        }
        $existingNew = $templates->get($new);
        if ($existingNew && $existingNew->id) {
            error("A template named '{$new}' already exists.");
            return Command::FAILURE;
        }

        $result = ['old' => $old, 'new' => $new, 'dryRun' => $dryRun];
        if ($dryRun) {
            if ($asJson) {
                $output->writeln(json_encode(['ok' => true, 'data' => $result], JSON_UNESCAPED_SLASHES));
            } else {
                note("Dry-run: would rename template '{$old}' to '{$new}'.");
            }
            return Command::SUCCESS;
        }

        $t->name = $new;
        $t->save();

        if ($asJson) {
            $output->writeln(json_encode(['ok' => true, 'data' => $result + ['renamed' => true]], JSON_UNESCAPED_SLASHES));
        } else {
            info("Renamed template '{$old}' to '{$new}'.");
        }
        return Command::SUCCESS;
    }
}

