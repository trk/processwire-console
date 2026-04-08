<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use function Laravel\Prompts\table;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

final class FieldListCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('field:list')
            ->setDescription('List all fields in the ProcessWire installation.')
            ->addOption('type', 't', InputOption::VALUE_REQUIRED, 'Filter by field type (e.g., FieldtypeText)')
            ->addOption('search', 's', InputOption::VALUE_REQUIRED, 'Search fields by name or label')
            ->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $type = $input->getOption('type');
        $search = $input->getOption('search');

        $fields = \ProcessWire\wire('fields');
        $filtered = [];

        foreach ($fields as $field) {
            // Apply filtering
            if ($type && stripos($field->type->className(), $type) === false) continue;
            if ($search && (stripos($field->name, $search) === false && stripos($field->label, $search) === false)) continue;

            $filtered[] = $field;
        }

        if (empty($filtered)) {
            warning("No fields found matching criteria.");
            return Command::SUCCESS;
        }

        $asJson = (bool)$input->getOption('json');
        if ($asJson) {
            $items = [];
            foreach ($filtered as $field) {
                $templates = array_map(fn($t) => $t->name, iterator_to_array($field->getTemplates()));
                $flags = [];
                if ($field->flags & \ProcessWire\Field::flagSystem) $flags[] = 'sys';
                if ($field->flags & \ProcessWire\Field::flagPermanent) $flags[] = 'perm';
                $items[] = [
                    'id' => $field->id,
                    'name' => $field->name,
                    'type' => $field->type->className(),
                    'label' => (string)$field->label,
                    'contexts' => count($templates),
                    'flags' => $flags,
                ];
            }
            $output->writeln(json_encode(['ok' => true, 'data' => ['items' => $items, 'total' => count($filtered)]], JSON_UNESCAPED_SLASHES));
        } else {
            $headers = ['ID', 'Name', 'Type', 'Label', 'Contexts', 'Flags'];
            $rows = [];
            
            foreach ($filtered as $field) {
                $templates = array_map(fn($t) => $t->name, iterator_to_array($field->getTemplates()));
                $flags = [];
                if ($field->flags & \ProcessWire\Field::flagSystem) $flags[] = 'sys';
                if ($field->flags & \ProcessWire\Field::flagPermanent) $flags[] = 'perm';
                
                $rows[] = [
                    (string) $field->id,
                    $field->name,
                    $field->type->className(),
                    mb_strimwidth((string)$field->label, 0, 30, '...'),
                    (string) count($templates),
                    implode('|', $flags) ?: '-'
                ];
            }
            
            table(headers: $headers, rows: $rows);
            info("Total fields: " . count($filtered));
        }

        return Command::SUCCESS;
    }
}
