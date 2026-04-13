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
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\spin;
use Totoglu\Console\Traits\InteractWithProcessWire;

final class ModuleUninstallCommand extends Command
{
    use InteractWithProcessWire;
    protected function configure(): void
    {
        $this
            ->setName('module:uninstall')
            ->setDescription('Uninstall a ProcessWire module by class name.')
            ->addOption('name', null, InputOption::VALUE_OPTIONAL, 'Module class name')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write changes')
            ->addOption('json', null, InputOption::VALUE_NONE, 'JSON output')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip interactive confirmations');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getOption('name') ? (string)$input->getOption('name') : '';
        $dryRun = (bool)$input->getOption('dry-run');
        $asJson = (bool)$input->getOption('json');
        $force = (bool)$input->getOption('force');

        if (!$name && !$asJson) {
            $name = $this->searchInstalledModule('Select a module to uninstall');
            if ($name === 'No matching modules found') return Command::SUCCESS;
        }

        if (!$name) {
            error("Provide --name.");
            return Command::FAILURE;
        }

        $modules = \ProcessWire\wire('modules');
        $installed = $modules->isInstalled($name);
        if (!$installed) {
            if ($asJson) {
                $output->writeln(json_encode(['ok' => true, 'data' => ['name' => $name, 'alreadyUninstalled' => true]], JSON_UNESCAPED_SLASHES));
            } else {
                warning("Module '{$name}' is not installed.");
            }
            return Command::SUCCESS;
        }

        if (!$force && !$asJson && !$dryRun) {
            if (!confirm("Uninstall module '{$name}'?", false)) {
                note("Aborted.");
                return Command::SUCCESS;
            }
        }

        $result = ['name' => $name, 'dryRun' => $dryRun];
        if ($dryRun) {
            if ($asJson) {
                $output->writeln(json_encode(['ok' => true, 'data' => $result], JSON_UNESCAPED_SLASHES));
            } else {
                note("Dry-run: would uninstall module '{$name}'.");
            }
            return Command::SUCCESS;
        }

        try {
            if ($asJson) {
                $modules->uninstall($name);
            } else {
                info("Uninstalling module '{$name}'...");
                $modules->uninstall($name);
            }
        } catch (\Throwable $e) {
            $msg = "Uninstall failed for module '{$name}': " . $e->getMessage();
            if ($asJson) {
                $output->writeln(json_encode(['ok' => false, 'error' => ['code' => 'UNINSTALL_FAILED', 'message' => $msg]], JSON_UNESCAPED_SLASHES));
            } else {
                error($msg);
            }
            return Command::FAILURE;
        }

        if ($asJson) {
            $output->writeln(json_encode(['ok' => true, 'data' => $result + ['uninstalled' => true]], JSON_UNESCAPED_SLASHES));
        } else {
            info("Uninstalled module '{$name}'.");
        }
        return Command::SUCCESS;
    }
}

