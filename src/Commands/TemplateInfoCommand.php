<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class TemplateInfoCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('template:info')
            ->setDescription('Show detailed information about a template.')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the template');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getArgument('name');

        $template = \ProcessWire\wire('templates')->get($name);

        if (!$template || !$template->id) {
            $io->error("Template '{$name}' not found.");
            return Command::FAILURE;
        }

        $io->title("Template Structure: {$template->name}");

        $general = [
            "ID" => $template->id,
            "Name" => $template->name,
            "Label" => $template->label ?: '-',
            "Tag" => $template->tags ?: '-',
            "File" => $template->filename ?: 'No file',
            "Fields" => count($template->fields ?: []),
            "Pages" => \ProcessWire\wire('pages')->count("template={$template->name}, include=all"),
        ];

        $io->definitionList(...array_map(fn($k, $v) => [$k => $v], array_keys($general), array_values($general)));

        // Fields
        $io->section("Fields (Order)");
        $fieldList = [];
        foreach ($template->fields as $f) {
            $fieldList[] = "{$f->name} ({$f->type->className()})";
        }
        $io->listing($fieldList ?: ['No fields defined']);

        // Access (Permissions)
        $io->section("Access Control");
        if ($template->useRoles) {
            $io->text("View Roles: " . implode(', ', array_map(fn($r) => $r->name, iterator_to_array($template->roles))));
            $io->text("Edit Roles: " . implode(', ', array_map(fn($r) => $r->name, iterator_to_array($template->editRoles))));
        } else {
            $io->text("Inheriting access from parent.");
        }

        // Advanced
        $flags = [];
        if ($template->flags & \ProcessWire\Template::flagSystem) $flags[] = 'System';
        if ($template->noParents) $flags[] = 'No Parents';
        if ($template->noChildren) $flags[] = 'No Children';

        if ($flags) {
            $io->section("Technical Flags");
            $io->text(implode(' | ', $flags));
        }

        return Command::SUCCESS;
    }
}
