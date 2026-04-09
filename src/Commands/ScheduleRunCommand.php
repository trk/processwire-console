<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Scheduling\ScheduleManager;
use Totoglu\Console\Scheduling\Task;
use Totoglu\Console\Scheduling\Event;

final class ScheduleRunCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('schedule:run')
            ->setDescription('Run the scheduled commands');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $wire = \ProcessWire\wire();
        if (!$wire) {
            \Laravel\Prompts\error("ProcessWire environment not found.");
            return Command::FAILURE;
        }

        $manager = new ScheduleManager($wire);
        $tasks = $manager->getAvailableTasks();

        if (empty($tasks)) {
            \Laravel\Prompts\info("No scheduled tasks found.");
            return Command::SUCCESS;
        }

        $ranAny = false;

        foreach ($tasks as $name => $path) {
            require_once $path;

            // Parse namespace
            $namespace = '';
            $content = (string)file_get_contents($path);
            if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
                $namespace = trim($matches[1]) . chr(92);
            }

            $fullClassName = $namespace . $name;

            if (!class_exists($fullClassName)) {
                continue;
            }

            /** @var Task $instance */
            $instance = new $fullClassName($wire);
            $event = new Event();
            
            // Build the schedule via the task's schedule logic
            $instance->schedule($event);

            if ($event->isDue()) {
                $ranAny = true;
                \Laravel\Prompts\info("Running scheduled task: {$name}");
                try {
                    $instance->handle();
                } catch (\Throwable $e) {
                    \Laravel\Prompts\error("Task {$name} failed: " . $e->getMessage());
                }
            }
        }

        if (!$ranAny) {
            \Laravel\Prompts\info('No scheduled commands are ready to run.');
        }

        return Command::SUCCESS;
    }
}
