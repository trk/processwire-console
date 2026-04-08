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
use function Laravel\Prompts\text;
use Totoglu\Console\Traits\InteractWithProcessWire;

final class TemplateUpdateCommand extends Command
{
    use InteractWithProcessWire;
    protected function configure(): void
    {
        $this
            ->setName('template:update')
            ->setDescription('Update template settings (e.g., tags, flags).')
            ->addOption('name', null, InputOption::VALUE_OPTIONAL, 'Template name')
            ->addOption('set', null, InputOption::VALUE_OPTIONAL, 'Comma-separated key=value pairs')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write changes')
            ->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getOption('name');
        $asJson = (bool)$input->getOption('json');
        
        if (!$name && !$asJson) {
            $name = $this->searchTemplate('Select the template to update');
            if ($name === 'No matching templates found') return Command::SUCCESS;
        }

        if (!$name) {
            error("Provide --name.");
            return Command::FAILURE;
        }

        $set = $input->getOption('set') ? (string)$input->getOption('set') : '';
        if (!$set && !$asJson) {
            $set = text(
                label: 'Enter key=value pairs to set',
                placeholder: 'tags=content,flags=1',
                required: true
            );
        }

        $dryRun = (bool)$input->getOption('dry-run');

        $template = \ProcessWire\wire('templates')->get($name);
        if (!$template || !$template->id) {
            error("Template not found: {$name}");
            return Command::FAILURE;
        }

        $changes = [];
        if ($set) {
            foreach (explode(',', $set) as $pair) {
                $pair = trim($pair);
                if ($pair === '') continue;
                $parts = explode('=', $pair, 2);
                if (count($parts) !== 2) continue;
                $k = trim($parts[0]);
                $v = trim($parts[1]);
                $changes[$k] = $v;
            }
        }

        if (empty($changes)) {
            error("No valid key=value pairs provided.");
            return Command::FAILURE;
        }

        $result = ['name' => $name, 'changes' => $changes, 'dryRun' => $dryRun];
        if ($dryRun) {
            if ($asJson) {
                $output->writeln(json_encode(['ok' => true, 'data' => $result], JSON_UNESCAPED_SLASHES));
            } else {
                note("Dry-run: would update template '{$name}' with: " . json_encode($changes));
            }
            return Command::SUCCESS;
        }

        foreach ($changes as $k => $v) {
            try {
                $template->set($k, $v);
            } catch (\Throwable $e) {
                warning("Property '{$k}': " . $e->getMessage());
            }
        }
        $template->save();

        if ($asJson) {
            $output->writeln(json_encode(['ok' => true, 'data' => $result + ['saved' => true]], JSON_UNESCAPED_SLASHES));
        } else {
            info("Updated template '{$name}'.");
        }
        return Command::SUCCESS;
    }
}

