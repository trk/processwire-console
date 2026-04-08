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
use function Laravel\Prompts\spin;
use Totoglu\Console\Traits\InteractWithProcessWire;

final class ModuleInstallCommand extends Command
{
    use InteractWithProcessWire;
    protected function configure(): void
    {
        $this
            ->setName('module:install')
            ->setDescription('Install a ProcessWire module by class name.')
            ->addOption('name', null, InputOption::VALUE_OPTIONAL, 'Module class name')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write changes')
            ->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getOption('name') ? (string)$input->getOption('name') : '';
        $dryRun = (bool)$input->getOption('dry-run');
        $asJson = (bool)$input->getOption('json');

        if (!$name && !$asJson) {
            $name = $this->searchInstallableModule('Select a module to install');
            if ($name === 'No matching installable modules found') return Command::SUCCESS;
        }

        if (!$name) {
            error("Provide --name.");
            return Command::FAILURE;
        }

        $modules = \ProcessWire\wire('modules');
        $installed = $modules->isInstalled($name);
        $result = ['name' => $name, 'alreadyInstalled' => $installed, 'dryRun' => $dryRun];

        if ($dryRun) {
            if ($asJson) {
                $output->writeln(json_encode(['ok' => true, 'data' => $result], JSON_UNESCAPED_SLASHES));
            } else {
                note("Dry-run: would install module '{$name}'.");
            }
            return Command::SUCCESS;
        }

        if ($installed) {
            if ($asJson) {
                $output->writeln(json_encode(['ok' => true, 'data' => $result], JSON_UNESCAPED_SLASHES));
            } else {
                info("Module '{$name}' is already installed.");
            }
            return Command::SUCCESS;
        }

        try {
            if ($asJson) {
                $modules->install($name);
            } else {
                spin(
                    fn () => $modules->install($name),
                    "Installing module '{$name}'..."
                );
            }
        } catch (\Throwable $e) {
            $msg = "Install failed for module '{$name}': " . $e->getMessage();
            if ($asJson) {
                $output->writeln(json_encode(['ok' => false, 'error' => ['code' => 'INSTALL_FAILED', 'message' => $msg]], JSON_UNESCAPED_SLASHES));
            } else {
                error($msg);
            }
            return Command::FAILURE;
        }

        if ($asJson) {
            $output->writeln(json_encode(['ok' => true, 'data' => $result + ['installed' => true]], JSON_UNESCAPED_SLASHES));
        } else {
            info("Installed module '{$name}'.");
        }
        return Command::SUCCESS;
    }
}

