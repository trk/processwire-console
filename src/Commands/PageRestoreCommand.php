<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class PageRestoreCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('page:restore')
            ->setDescription('Restore a trashed page to its original location.')
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Page ID')
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'Page path')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write changes')
            ->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $id = $input->getOption('id');
        $path = $input->getOption('path');
        $dryRun = (bool)$input->getOption('dry-run');
        $asJson = (bool)$input->getOption('json');

        if (!$id && !$path) {
            $io->error("Provide --id or --path.");
            return Command::FAILURE;
        }

        $pages = \ProcessWire\wire('pages');
        $page = $id ? $pages->get((int)$id) : $pages->get((string)$path);
        if (!$page || !$page->id) {
            $io->error("Page not found.");
            return Command::FAILURE;
        }

        $result = ['id' => $page->id, 'path' => $page->path, 'dryRun' => $dryRun];
        if ($dryRun) {
            if ($asJson) {
                $output->writeln(json_encode(['ok' => true, 'data' => $result], JSON_UNESCAPED_SLASHES));
            } else {
                $io->note("Dry-run: would restore page #{$page->id}.");
            }
            return Command::SUCCESS;
        }

        $ok = $pages->restore($page);
        if (!$ok) {
            $msg = "Restore failed for page #{$page->id}.";
            if ($asJson) {
                $output->writeln(json_encode(['ok' => false, 'error' => ['code' => 'RESTORE_FAILED', 'message' => $msg]], JSON_UNESCAPED_SLASHES));
            } else {
                $io->error($msg);
            }
            return Command::FAILURE;
        }

        if ($asJson) {
            $output->writeln(json_encode(['ok' => true, 'data' => $result + ['restored' => true, 'path' => $page->path]], JSON_UNESCAPED_SLASHES));
        } else {
            $io->success("Restored page #{$page->id} to {$page->path}.");
        }
        return Command::SUCCESS;
    }
}

