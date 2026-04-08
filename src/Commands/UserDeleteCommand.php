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

final class UserDeleteCommand extends Command
{
    use InteractWithProcessWire;
    protected function configure(): void
    {
        $this
            ->setName('user:delete')
            ->setDescription('Delete a user.')
            ->addOption('id', null, InputOption::VALUE_OPTIONAL, 'User ID')
            ->addOption('name', null, InputOption::VALUE_OPTIONAL, 'User name')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write changes')
            ->addOption('json', null, InputOption::VALUE_NONE, 'JSON output')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip interactive confirmations');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getOption('id');
        $username = $input->getOption('name') ? (string)$input->getOption('name') : '';
        $dryRun = (bool)$input->getOption('dry-run');
        $asJson = (bool)$input->getOption('json');
        $force = (bool)$input->getOption('force');

        if (!$id && !$username && !$asJson) {
            $username = $this->searchUser('Select a user to delete');
            if ($username === 'No matching users found') return Command::SUCCESS;
        }

        if (!$id && !$username) {
            error("Provide --id or --name.");
            return Command::FAILURE;
        }

        $users = \ProcessWire\wire('users');
        $user = $id ? $users->get((int)$id) : $users->get((string)$username);
        if (!$user || !$user->id) {
            error("User not found.");
            return Command::FAILURE;
        }
        if ($user->isSuperuser()) {
            error("Refusing to delete a superuser.");
            return Command::FAILURE;
        }

        if (!$force && !$asJson && !$dryRun) {
            if (!confirm("Delete user '{$user->name}' (ID {$user->id})?", false)) {
                note("Aborted.");
                return Command::SUCCESS;
            }
        }

        $result = ['id' => $user->id, 'name' => $user->name, 'dryRun' => $dryRun];
        if ($dryRun) {
            if ($asJson) {
                $output->writeln(json_encode(['ok' => true, 'data' => $result], JSON_UNESCAPED_SLASHES));
            } else {
                note("Dry-run: would delete user '{$user->name}'.");
            }
            return Command::SUCCESS;
        }

        try {
            $users->delete($user);
        } catch (\Throwable $e) {
            $msg = "Delete failed for user '{$user->name}': " . $e->getMessage();
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
            info("Deleted user '{$user->name}'.");
        }
        return Command::SUCCESS;
    }
}

