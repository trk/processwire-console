<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class UserListCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('user:list')
            ->setDescription('List users in the ProcessWire installation.')
            ->addOption('search', null, InputOption::VALUE_REQUIRED, 'Search by name contains')
            ->addOption('role', null, InputOption::VALUE_REQUIRED, 'Filter by role name')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Number of users to show', '50')
            ->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $search = $input->getOption('search') ? (string)$input->getOption('search') : '';
        $role = $input->getOption('role') ? (string)$input->getOption('role') : '';
        $limit = (int)$input->getOption('limit');

        $selector = [];
        if ($search) $selector[] = "name%=$search";
        if ($role) $selector[] = "roles=$role";
        if ($limit) $selector[] = "limit=$limit";
        if (!$selector) $selector[] = "limit=$limit";

        $users = \ProcessWire\wire('users')->find(implode(', ', $selector));
        if (!$users->count()) {
            $io->warning("No users found.");
            return Command::SUCCESS;
        }

        $asJson = (bool)$input->getOption('json');
        if ($asJson) {
            $items = [];
            foreach ($users as $user) {
                $roles = [];
                foreach ($user->roles as $r) $roles[] = $r->name;
                $flags = [];
                if ($user->isSuperuser()) $flags[] = 'superuser';
                if ($user->isUnpublished()) $flags[] = 'unpub';
                $items[] = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => (string)($user->email ?? ''),
                    'roles' => $roles,
                    'flags' => $flags ?: ['ok'],
                ];
            }
            $output->writeln(json_encode(['ok' => true, 'data' => ['items' => $items, 'limit' => $limit]], JSON_UNESCAPED_SLASHES));
        } else {
            $table = new Table($output);
            $table->setHeaders(['ID', 'Name', 'Email', 'Roles', 'Flags']);
            foreach ($users as $user) {
                $roles = [];
                foreach ($user->roles as $r) $roles[] = $r->name;
                $flags = [];
                if ($user->isSuperuser()) $flags[] = 'superuser';
                if ($user->isUnpublished()) $flags[] = 'unpub';
                $table->addRow([
                    $user->id,
                    $user->name,
                    (string)($user->email ?? ''),
                    implode(',', $roles),
                    implode('|', $flags) ?: 'ok',
                ]);
            }
            $table->render();
            $io->note("Showing up to $limit user(s).");
        }
        return Command::SUCCESS;
    }
}
