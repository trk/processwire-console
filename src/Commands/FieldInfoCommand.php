<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use ProcessWire\Field;
use Totoglu\Console\Traits\InteractWithProcessWire;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;
use function ProcessWire\wire;

final class FieldInfoCommand extends Command
{
    use InteractWithProcessWire;

    protected function configure(): void
    {
        $this
            ->setName('field:info')
            ->setDescription('Show detailed information about a field, including technical details.')
            ->addArgument('name', InputArgument::OPTIONAL, 'The name of the field');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name') ?? $this->searchField('Search for a field to view info');

        if ($name === 'No matching fields found') {
            return Command::SUCCESS;
        }

        $field = wire('fields')->get($name);

        if (!$field || !$field->id) {
            error("Field '{$name}' not found.");
            return Command::FAILURE;
        }

        info("Field Technical Insights: {$field->name}");

        $general = [
            ["ID", (string) $field->id],
            ["Name", $field->name],
            ["Label", $field->label ?: '-'],
            ["Type", $field->type->className()],
            ["Table", $field->getTable()],
            ["Flags", $this->getFlagSummary($field->flags)],
        ];

        table(
            headers: ['Property', 'Value'],
            rows: $general
        );

        // Templates
        $templates = array_map(fn($t) => $t->name, iterator_to_array($field->getTemplates()));
        info("Template Usage (" . count($templates) . ")");
        if ($templates) {
            info(implode(', ', $templates));
        } else {
            warning('Not used in any templates');
        }

        // Data Structure
        info("Data Structure");
        $tableName = $field->getTable();
        try {
            $db = wire('database');
            $safeTableName = $db->escapeTable($tableName);
            $query = $db->prepare("SHOW COLUMNS FROM `{$safeTableName}`");
            $query->execute();
            $tableFields = $query->fetchAll(\PDO::FETCH_ASSOC);
            $columnsData = array_map(fn($col) => [$col['Field'], $col['Type']], $tableFields);
            table(
                headers: ['Column', 'DataType'],
                rows: $columnsData
            );
        } catch (\Exception $e) {
            warning("Could not fetch table structure: " . $e->getMessage());
        }

        // Settings (Compact)
        info("Settings (Non-Default)");
        $data = $field->getArray();
        $settingsData = [];
        foreach ($data as $k => $v) {
            if (is_array($v)) $v = 'Array(...)';
            if (is_object($v)) $v = get_class($v);
            if (strlen((string)$v) > 100) $v = substr((string)$v, 0, 97) . '...';
            $settingsData[] = [$k, (string) $v];
        }

        $slicedSettings = array_slice($settingsData, 0, 15);
        if ($slicedSettings) {
            table(['Setting', 'Value'], $slicedSettings);
        }

        if (count($settingsData) > 15) {
            info("... and " . (count($settingsData) - 15) . " more.");
        }

        return Command::SUCCESS;
    }

    private function getFlagSummary(int $flags): string
    {
        $summary = [];
        if ($flags & Field::flagSystem) $summary[] = 'System';
        if ($flags & Field::flagPermanent) $summary[] = 'Permanent';
        if ($flags & Field::flagGlobal) $summary[] = 'Global';
        if ($flags & Field::flagAutojoin) $summary[] = 'Autojoin';

        return $summary ? implode(' | ', $summary) : 'None';
    }
}
