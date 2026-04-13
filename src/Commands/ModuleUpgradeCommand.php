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
use function Laravel\Prompts\spin;
use Totoglu\Console\Traits\InteractWithProcessWire;

final class ModuleUpgradeCommand extends Command
{
    use InteractWithProcessWire;
    protected function configure(): void
    {
        $this
            ->setName('module:upgrade')
            ->setDescription('Attempt to upgrade a ProcessWire module (refresh and reinstall if needed).')
            ->addOption('name', null, InputOption::VALUE_OPTIONAL, 'Module class name')
            ->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getOption('name') ? (string)$input->getOption('name') : '';
        $asJson = (bool)$input->getOption('json');

        if (!$name && !$asJson) {
            $name = $this->searchInstalledModule('Select a module to upgrade');
            if ($name === 'No matching modules found') return Command::SUCCESS;
        }

        if (!$name) {
            error("Provide --name.");
            return Command::FAILURE;
        }

        $modules = \ProcessWire\wire('modules');
        $modules->refresh();
        $installed = $modules->isInstalled($name);
        if ($installed) {
            // Re-run install may trigger upgrade routines if module defines them
            try {
                if ($asJson) {
                    $modules->install($name);
                } else {
                    info("Upgrading module '{$name}'...");
                    $modules->install($name);
                }
            } catch (\Throwable $e) {
                $msg = "Upgrade step failed for '{$name}': " . $e->getMessage();
                if ($asJson) {
                    $output->writeln(json_encode(['ok' => false, 'error' => ['code' => 'UPGRADE_FAILED', 'message' => $msg]], JSON_UNESCAPED_SLASHES));
                } else {
                    error($msg);
                }
                return Command::FAILURE;
            }
        }

        $data = ['name' => $name, 'refreshed' => true, 'upgraded' => $installed];
        if ($asJson) {
            $output->writeln(json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_SLASHES));
        } else {
            info("Modules refreshed" . ($installed ? " and '{$name}' upgrade routine executed" : "") . ".");
        }
        return Command::SUCCESS;
    }
}

