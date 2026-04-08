<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;

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
        $io = new SymfonyStyle($input, $output);
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
            $io->warning("No fields found matching criteria.");
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
            $table = new Table($output);
            $table->setHeaders(['ID', 'Name', 'Type', 'Label', 'Contexts', 'Flags']);
            foreach ($filtered as $field) {
                $templates = array_map(fn($t) => $t->name, iterator_to_array($field->getTemplates()));
                $flags = [];
                if ($field->flags & \ProcessWire\Field::flagSystem) $flags[] = '<fg=red>sys</>';
                if ($field->flags & \ProcessWire\Field::flagPermanent) $flags[] = '<fg=yellow>perm</>';
                $table->addRow([
                    $field->id,
                    $field->name,
                    $field->type->className(),
                    mb_strimwidth($field->label, 0, 30, '...'),
                    count($templates),
                    implode('|', $flags) ?: '-'
                ]);
            }
            $table->render();
            $io->note("Total fields: " . count($filtered));
        }

        return Command::SUCCESS;
    }
}
