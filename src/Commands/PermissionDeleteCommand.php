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
use function Laravel\Prompts\confirm;
use Totoglu\Console\Traits\InteractWithProcessWire;

final class PermissionDeleteCommand extends Command
{
    use InteractWithProcessWire;
    protected function configure(): void
    {
        $this
            ->setName('permission:delete')
            ->setDescription('Delete a custom permission.')
            ->addOption('name', null, InputOption::VALUE_OPTIONAL, 'Permission name')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write changes')
            ->addOption('json', null, InputOption::VALUE_NONE, 'JSON output')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip interactive confirmations');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $permName = $input->getOption('name') ? (string)$input->getOption('name') : '';
        $dryRun = (bool)$input->getOption('dry-run');
        $asJson = (bool)$input->getOption('json');
        $force = (bool)$input->getOption('force');

        if (!$permName && !$asJson) {
            $permName = $this->searchPermission('Select a permission to delete');
            if ($permName === 'No matching permissions found') return Command::SUCCESS;
        }

        if (!$permName) {
            error("Provide --name.");
            return Command::FAILURE;
        }

        $permissions = \ProcessWire\wire('permissions');
        $permission = $permissions->get($permName);
        if (!$permission || !$permission->id) {
            error("Permission not found: {$permName}");
            return Command::FAILURE;
        }

        if (!$force && !$asJson && !$dryRun) {
            if (!confirm("Delete permission '{$permName}'?", false)) {
                note("Aborted.");
                return Command::SUCCESS;
            }
        }

        $result = ['name' => $permName, 'dryRun' => $dryRun];
        if ($dryRun) {
            if ($asJson) {
                $output->writeln(json_encode(['ok' => true, 'data' => $result], JSON_UNESCAPED_SLASHES));
            } else {
                note("Dry-run: would delete permission '{$permName}'.");
            }
            return Command::SUCCESS;
        }

        try {
            $permissions->delete($permission);
        } catch (\Throwable $e) {
            $msg = "Delete failed for permission '{$permName}': " . $e->getMessage();
            if ($asJson) {
                $output->writeln(json_encode(['ok' => false, 'error' => ['code' => 'DELETE_FAILED', 'message' => $msg]], JSON_UNESCAPED_SLASHES));
            } else {
                error($msg);
            }
            return Command::FAILURE;
        }

        if ($asJson) {
            $output->writeln(json_encode(['ok' => true, 'data' => $result + ['deleted' => true]], JSON_UNESCAPED_SLASHES));
        } else {
            info("Deleted permission '{$permName}'.");
        }
        return Command::SUCCESS;
    }
}
