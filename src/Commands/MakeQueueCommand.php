<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class MakeQueueCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('make:queue')
            ->setDescription('Create a new Queue class.')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the Queue class (e.g. SendEmailQueue)')
            ->addOption('module', 'm', InputOption::VALUE_REQUIRED, 'Module name to place the queue in (e.g. MyCustomModule)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = (string)$input->getArgument('name');
        
        // Ensure name ends with Queue
        if (!str_ends_with($name, 'Queue')) {
            $name .= 'Queue';
        }

        $module = $input->getOption('module') ? (string)$input->getOption('module') : null;

        $config = \ProcessWire\wire('config');
        
        // Target directory determination
        if ($module) {
            $targetDir = $config->paths->siteModules . $module . '/queue/';
            $namespace = "ProcessWire"; // Standard processwire module namespace
        } else {
            $targetDir = $config->paths->site . 'queue/';
            $namespace = "Site\\Queue"; // Standard site namespace for queues
        }

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $filePath = $targetDir . $name . '.php';

        if (file_exists($filePath)) {
            \Laravel\Prompts\error("Queue file already exists: {$name}.php");
            return Command::FAILURE;
        }

        $stubPath = __DIR__ . '/../../resources/stubs/queue.stub';
        
        if (!file_exists($stubPath)) {
            \Laravel\Prompts\error("Stub file not found: queue.stub");
            return Command::FAILURE;
        }

        $content = (string)file_get_contents($stubPath);

        // Replacements
        $content = str_replace(
            ['{{namespace}}', '{{class}}'],
            [$namespace, $name],
            $content
        );

        file_put_contents($filePath, $content);

        \Laravel\Prompts\info("Created queue class: {$name}");
        \Laravel\Prompts\note("Path: {$filePath}");

        return Command::SUCCESS;
    }
}
