<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class FieldInfoCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('field:info')
            ->setDescription('Show detailed information about a field, including technical details.')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the field');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getArgument('name');

        $field = \ProcessWire\wire('fields')->get($name);

        if (!$field || !$field->id) {
            $io->error("Field '{$name}' not found.");
            return Command::FAILURE;
        }

        $io->title("Field Technical Insights: {$field->name}");

        $general = [
            "ID" => $field->id,
            "Name" => $field->name,
            "Label" => $field->label ?: '-',
            "Type" => $field->type->className(),
            "Table" => $field->getTable(),
            "Flags" => $this->getFlagSummary($field->flags),
        ];

        $io->definitionList(...array_map(fn($k, $v) => [$k => $v], array_keys($general), array_values($general)));

        // Templates
        $templates = array_map(fn($t) => $t->name, iterator_to_array($field->getTemplates()));
        $io->section("Template Usage (" . count($templates) . ")");
        $io->text($templates ? implode(', ', $templates) : 'Not used in any templates');

        // Data Structure
        $io->section("Data Structure");
        $tableFields = \ProcessWire\wire('db')->getColumns($field->getTable());
        $io->listing(array_map(fn($col) => "{$col['Field']} ({$col['Type']})", $tableFields));

        // Settings (Compact)
        $io->section("Settings (Non-Default)");
        $data = $field->getArray();
        $settings = [];
        foreach ($data as $k => $v) {
            if (is_array($v)) $v = 'Array(...)';
            if (is_object($v)) $v = get_class($v);
            if (strlen((string)$v) > 100) $v = substr((string)$v, 0, 97) . '...';
            $settings[] = "{$k}: {$v}";
        }
        $io->text(array_slice($settings, 0, 15)); // Show first 15 settings
        if (count($settings) > 15) $io->text("... and " . (count($settings) - 15) . " more.");

        return Command::SUCCESS;
    }

    private function getFlagSummary(int $flags): string
    {
        $summary = [];
        if ($flags & \ProcessWire\Field::flagSystem) $summary[] = 'System';
        if ($flags & \ProcessWire\Field::flagPermanent) $summary[] = 'Permanent';
        if ($flags & \ProcessWire\Field::flagGlobal) $summary[] = 'Global';
        if ($flags & \ProcessWire\Field::flagAutojoin) $summary[] = 'Autojoin';

        return $summary ? implode(' | ', $summary) : 'None';
    }
}
