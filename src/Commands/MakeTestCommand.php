<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class MakeTestCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('make:test')
            ->setDescription('Create a new Pest test file.')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the test file (e.g. UserTest)')
            ->addOption('unit', 'u', InputOption::VALUE_NONE, 'Create a unit test')
            ->addOption('module', 'm', InputOption::VALUE_REQUIRED, 'Module name to place the test in (e.g. MyCustomModule)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = (string)$input->getArgument('name');
        
        if (!str_ends_with($name, 'Test')) {
            $name .= 'Test';
        }

        $module = $input->getOption('module') ? (string)$input->getOption('module') : null;
        $isUnit = $input->getOption('unit');

        $config = \ProcessWire\wire('config');
        
        $subFolder = $isUnit ? 'Unit/' : 'Feature/';

        if ($module) {
            $targetDir = rtrim($config->paths->siteModules, '/') . '/' . $module . '/tests/' . $subFolder;
        } else {
            // Default to site/tests/
            $targetDir = rtrim($config->paths->site, '/') . '/tests/' . $subFolder;
        }

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $filePath = $targetDir . $name . '.php';

        if (file_exists($filePath)) {
            \Laravel\Prompts\error("Test file already exists: {$name}.php");
            return Command::FAILURE;
        }

        $stubPath = __DIR__ . '/../../resources/stubs/pest.stub';
        
        if (!file_exists($stubPath)) {
            \Laravel\Prompts\error("Stub file not found: pest.stub");
            return Command::FAILURE;
        }

        $content = (string)file_get_contents($stubPath);

        // Standard pest test has no classes, so we can just drop it directly.
        // If we wanted to replace generic names, we could.
        file_put_contents($filePath, $content);

        \Laravel\Prompts\info("Created test file: {$name}");
        \Laravel\Prompts\note("Path: {$filePath}");

        return Command::SUCCESS;
    }
}
