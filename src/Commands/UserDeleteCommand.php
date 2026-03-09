<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class UserDeleteCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('user:delete')
            ->setDescription('Delete a user.')
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'User ID')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'User name')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write changes')
            ->addOption('json', null, InputOption::VALUE_NONE, 'JSON output')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip interactive confirmations');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $id = $input->getOption('id');
        $username = $input->getOption('name') ? (string)$input->getOption('name') : '';
        $dryRun = (bool)$input->getOption('dry-run');
        $asJson = (bool)$input->getOption('json');
        $force = (bool)$input->getOption('force');

        if (!$id && !$username) {
            $io->error("Provide --id or --name.");
            return Command::FAILURE;
        }

        $users = \ProcessWire\wire('users');
        $user = $id ? $users->get((int)$id) : $users->get((string)$username);
        if (!$user || !$user->id) {
            $io->error("User not found.");
            return Command::FAILURE;
        }
        if ($user->isSuperuser()) {
            $io->error("Refusing to delete a superuser.");
            return Command::FAILURE;
        }

        if (!$force && !$asJson && !$dryRun) {
            if (!$io->confirm("Delete user '{$user->name}' (ID {$user->id})?", false)) {
                $io->note("Aborted.");
                return Command::SUCCESS;
            }
        }

        $result = ['id' => $user->id, 'name' => $user->name, 'dryRun' => $dryRun];
        if ($dryRun) {
            if ($asJson) {
                $output->writeln(json_encode(['ok' => true, 'data' => $result], JSON_UNESCAPED_SLASHES));
            } else {
                $io->note("Dry-run: would delete user '{$user->name}'.");
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
                $io->error($msg);
            }
            return Command::FAILURE;
        }

        if ($asJson) {
            $output->writeln(json_encode(['ok' => true, 'data' => $result + ['deleted' => true]], JSON_UNESCAPED_SLASHES));
        } else {
            $io->success("Deleted user '{$user->name}'.");
        }
        return Command::SUCCESS;
    }
}

