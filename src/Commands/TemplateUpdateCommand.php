<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class TemplateUpdateCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('template:update')
            ->setDescription('Update template settings (e.g., tags, flags).')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Template name (required)')
            ->addOption('set', null, InputOption::VALUE_REQUIRED, 'Comma-separated key=value pairs')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write changes')
            ->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = (string)$input->getOption('name');
        $set = $input->getOption('set') ? (string)$input->getOption('set') : '';
        $dryRun = (bool)$input->getOption('dry-run');
        $asJson = (bool)$input->getOption('json');

        if (!$name) {
            $io->error("Provide --name.");
            return Command::FAILURE;
        }
        $template = \ProcessWire\wire('templates')->get($name);
        if (!$template || !$template->id) {
            $io->error("Template not found: {$name}");
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

        $result = ['name' => $name, 'changes' => $changes, 'dryRun' => $dryRun];
        if ($dryRun) {
            if ($asJson) {
                $output->writeln(json_encode(['ok' => true, 'data' => $result], JSON_UNESCAPED_SLASHES));
            } else {
                $io->note("Dry-run: would update template '{$name}' with: " . json_encode($changes));
            }
            return Command::SUCCESS;
        }

        foreach ($changes as $k => $v) {
            try {
                $template->set($k, $v);
            } catch (\Throwable $e) {
                // ignore invalid keys
            }
        }
        $template->save();

        if ($asJson) {
            $output->writeln(json_encode(['ok' => true, 'data' => $result + ['saved' => true]], JSON_UNESCAPED_SLASHES));
        } else {
            $io->success("Updated template '{$name}'.");
        }
        return Command::SUCCESS;
    }
}

