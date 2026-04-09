<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class MakeTaskCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('make:task')
            ->setDescription('Create a new Scheduled Task class.')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the Task class (e.g. CleanupLogsTask)')
            ->addOption('module', 'm', InputOption::VALUE_REQUIRED, 'Module name to place the task in (e.g. MyCustomModule)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = (string)$input->getArgument('name');
        
        if (!str_ends_with($name, 'Task')) {
            $name .= 'Task';
        }

        $module = $input->getOption('module') ? (string)$input->getOption('module') : null;

        $config = \ProcessWire\wire('config');
        
        if ($module) {
            $targetDir = $config->paths->siteModules . $module . '/schedule/';
            $namespace = "ProcessWire";
        } else {
            $targetDir = $config->paths->site . 'schedule/';
            $namespace = "Site\\Schedule";
        }

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $filePath = $targetDir . $name . '.php';

        if (file_exists($filePath)) {
            \Laravel\Prompts\error("Task file already exists: {$name}.php");
            return Command::FAILURE;
        }

        $stubPath = __DIR__ . '/../../resources/stubs/task.stub';
        
        if (!file_exists($stubPath)) {
            \Laravel\Prompts\error("Stub file not found: task.stub");
            return Command::FAILURE;
        }

        $content = (string)file_get_contents($stubPath);

        $content = str_replace(
            ['{{namespace}}', '{{class}}'],
            [$namespace, $name],
            $content
        );

        file_put_contents($filePath, $content);

        \Laravel\Prompts\info("Created scheduled task: {$name}");
        \Laravel\Prompts\note("Path: {$filePath}");

        return Command::SUCCESS;
    }
}
