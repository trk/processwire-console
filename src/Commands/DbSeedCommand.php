<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Database\SeederManager;
use Totoglu\Console\Database\Seeder;

final class DbSeedCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('db:seed')
            ->setDescription('Seed the database with records.')
            ->addOption('class', null, InputOption::VALUE_REQUIRED, 'The class name of the root seeder', 'DatabaseSeeder')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Force the operation to run');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $className = (string) $input->getOption('class');
        $force = (bool) $input->getOption('force');

        if (!$force && !\Laravel\Prompts\confirm('Are you sure you want to seed the database? This might insert bulk fake data.', default: false)) {
            \Laravel\Prompts\warning('Seeding cancelled.');
            return Command::SUCCESS;
        }

        $wire = \ProcessWire\wire();
        if (!$wire) {
            \Laravel\Prompts\error("ProcessWire environment not found.");
            return Command::FAILURE;
        }

        $manager = new SeederManager($wire);
        $seeders = $manager->getAvailableSeeders();

        // If a specific class is requested, check if we found it
        // Or if the specific default "DatabaseSeeder" isn't found, 
        // and we have others, let's just ask the user or run them all?
        // Standard Laravel logic: if --class is provided, run that. 
        // If it's the default "DatabaseSeeder" and we don't have it, run all or prompt.

        if (empty($seeders)) {
            \Laravel\Prompts\warning("No seeders found in site/seeders or modules.");
            return Command::SUCCESS;
        }

        if ($className !== 'DatabaseSeeder' && !isset($seeders[$className])) {
            \Laravel\Prompts\error("Seeder class not found: {$className}");
            return Command::FAILURE;
        }

        if (isset($seeders[$className])) {
            $this->runSeeder($wire, $className, $seeders[$className]);
        } else {
            // Run all seeders if DatabaseSeeder doesn't exist
            \Laravel\Prompts\info("Running all discovered seeders...");
            foreach ($seeders as $name => $path) {
                $this->runSeeder($wire, $name, $path);
            }
        }

        \Laravel\Prompts\info('Database seeding completed successfully.');

        return Command::SUCCESS;
    }

    private function runSeeder(\ProcessWire\ProcessWire $wire, string $name, string $path): void
    {
        require_once $path;

        // Parse namespace
        $namespace = '';
        $content = (string)file_get_contents($path);
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            $namespace = trim($matches[1]) . chr(92);
        }

        $fullClassName = $namespace . $name;

        if (!class_exists($fullClassName)) {
            \Laravel\Prompts\error("Class {$fullClassName} not found in {$path}");
            return;
        }

        \Laravel\Prompts\info("Seeding: {$name}...");
        /** @var Seeder $instance */
        $instance = new $fullClassName($wire);
        $instance->run();

        \Laravel\Prompts\note("Seeded: {$name}");
    }
}
