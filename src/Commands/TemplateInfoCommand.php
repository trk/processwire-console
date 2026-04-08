<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function Laravel\Prompts\error;
use function Laravel\Prompts\table;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use Totoglu\Console\Traits\InteractWithProcessWire;

final class TemplateInfoCommand extends Command
{
    use InteractWithProcessWire;
    protected function configure(): void
    {
        $this
            ->setName('template:info')
            ->setDescription('Show detailed information about a template.')
            ->addArgument('name', InputArgument::OPTIONAL, 'The name of the template');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        
        if (!$name) {
            $name = $this->searchTemplate('Select the template to view info');
            if ($name === 'No matching templates found') return Command::SUCCESS;
        }

        $template = \ProcessWire\wire('templates')->get($name);

        if (!$template || !$template->id) {
            error("Template '{$name}' not found.");
            return Command::FAILURE;
        }

        info("Template Structure: {$template->name}");

        $general = [
            "ID" => $template->id,
            "Name" => $template->name,
            "Label" => $template->label ?: '-',
            "Tag" => $template->tags ?: '-',
            "File" => $template->filename ?: 'No file',
            "Fields" => count($template->fields ?: []),
            "Pages" => \ProcessWire\wire('pages')->count("template={$template->name}, include=all"),
        ];

        table(
            headers: ['Property', 'Value'],
            rows: array_map(fn($k, $v) => [$k, $v], array_keys($general), array_values($general))
        );

        // Fields
        info("Fields (Order)");
        $fieldList = [];
        foreach ($template->fields as $f) {
            $fieldList[] = [$f->name, $f->type->className()];
        }
        if ($fieldList) {
            table(
                headers: ['Name', 'Type'],
                rows: $fieldList
            );
        } else {
            note("No fields defined");
        }

        // Access (Permissions)
        info("Access Control");
        if ($template->useRoles) {
            $accessRows = [];
            $accessRows[] = ['View Roles', implode(', ', array_map(fn($r) => $r->name, iterator_to_array($template->roles)))];
            $accessRows[] = ['Edit Roles', implode(', ', array_map(fn($r) => $r->name, iterator_to_array($template->editRoles)))];
            table(
                headers: ['Permission', 'Roles'],
                rows: $accessRows
            );
        } else {
            note("Inheriting access from parent.");
        }

        // Advanced
        $flags = [];
        if ($template->flags & \ProcessWire\Template::flagSystem) $flags[] = 'System';
        if ($template->noParents) $flags[] = 'No Parents';
        if ($template->noChildren) $flags[] = 'No Children';

        if ($flags) {
            info("Technical Flags");
            note(implode(' | ', $flags));
        }

        return Command::SUCCESS;
    }
}
