<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class CacheClearCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('cache:clear')
            ->setDescription('Clear various ProcessWire caches.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Clear module cache
        \ProcessWire\wire('modules')->resetCache();
        $io->info("Module cache cleared.");

        // Clear compiled templates
        $compiledPath = \ProcessWire\wire('config')->paths->cache . 'FileCompiler/';
        if (is_dir($compiledPath)) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($compiledPath, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $fileinfo) {
                $realPath = $fileinfo->getRealPath();
                if ($realPath === false) {
                    continue;
                }
                if ($fileinfo->isDir()) {
                    @rmdir($realPath);
                } else {
                    unlink($realPath);
                }
            }
            $io->info("Compiled templates (FileCompiler) cache cleared.");
        }

        $io->success("ProcessWire caches cleared successfully.");

        return Command::SUCCESS;
    }
}
