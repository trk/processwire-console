<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;
use function Laravel\Prompts\note;

final class ModuleListCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('module:list')
            ->setDescription('List installed modules in the ProcessWire installation.')
            ->addOption('search', 's', InputOption::VALUE_REQUIRED, 'Search modules by name or title')
            ->addOption('core', 'c', InputOption::VALUE_NONE, 'Show only core modules')
            ->addOption('site', null, InputOption::VALUE_NONE, 'Show only site modules (third-party)')
            ->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $search = $input->getOption('search');
        $coreOnly = $input->getOption('core');
        $siteOnly = $input->getOption('site');

        $modules = \ProcessWire\wire('modules');
        $installed = $modules->getAll();
        $filtered = [];

        foreach ($installed as $name => $module) {
            $info = $modules->getModuleInfo($name);

            // Path check for core vs site
            $isCore = str_contains($modules->getModuleFile($name), '/wire/modules/');

            if ($coreOnly && !$isCore) continue;
            if ($siteOnly && $isCore) continue;

            if ($search && (stripos($name, $search) === false && stripos($info['title'] ?? '', $search) === false)) continue;

            $filtered[$name] = $info;
        }

        if (empty($filtered)) {
            warning("No modules found matching criteria.");
            return Command::SUCCESS;
        }

        $asJson = (bool)$input->getOption('json');
        if ($asJson) {
            $items = [];
            foreach ($filtered as $name => $info) {
                $isCore = str_contains($modules->getModuleFile($name), '/wire/modules/');
                $items[] = [
                    'name' => $name,
                    'title' => (string)($info['title'] ?? ''),
                    'version' => $modules->formatVersion($info['version'] ?? 0),
                    'type' => $isCore ? 'core' : 'site',
                    'summary' => (string)($info['summary'] ?? ''),
                ];
            }
            $output->writeln(json_encode(['ok' => true, 'data' => ['items' => $items, 'total' => count($items)]], JSON_UNESCAPED_SLASHES));
        } else {
            $rows = [];
            foreach ($filtered as $name => $info) {
                $isCore = str_contains($modules->getModuleFile($name), '/wire/modules/');
                $type = $isCore ? 'Core' : 'Site';
                $versionStr = $modules->formatVersion($info['version'] ?? 0);
                $rows[] = [
                    $name,
                    mb_strimwidth((string)($info['title'] ?? '-'), 0, 35, '...'),
                    $versionStr,
                    $type,
                    mb_strimwidth((string)($info['summary'] ?? '-'), 0, 50, '...')
                ];
            }
            
            table(
                ['Name', 'Title', 'Version', 'Type', 'Summary'],
                $rows
            );
            
            note("Total modules listed: " . count($filtered));
        }

        return Command::SUCCESS;
    }
}
