<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Support\FeatureDiscoverer;

final class TestCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('test')
            ->setDescription('Run the application tests using Pest.')
            ->ignoreValidationErrors();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = \ProcessWire\wire('config');
        $projectRoot = dirname(rtrim($config->paths->site, '/'));
        $pestBinary = $projectRoot . '/vendor/bin/pest';

        if (!file_exists($pestBinary)) {
            $install = \Laravel\Prompts\confirm(
                label: 'Pest is not installed. Would you like to install it now via composer?',
                default: true
            );

            if ($install) {
                \Laravel\Prompts\info('Installing PestPHP...');
                $code = 0;
                passthru('composer require pestphp/pest --dev', $code);
                
                if ($code !== 0) {
                    \Laravel\Prompts\error('Failed to install Pest. Please install manually.');
                    return Command::FAILURE;
                }
                
                if (!is_dir($projectRoot . '/tests')) {
                    mkdir($projectRoot . '/tests');
                }
                
                // Init Pest
                passthru(escapeshellarg($pestBinary) . ' --init');
            } else {
                return Command::SUCCESS;
            }
        }

        $discoverer = new FeatureDiscoverer(\ProcessWire\wire());
        $moduleTestPaths = $discoverer->getFeaturePaths('tests');
        
        $executionPaths = [];

        $rootTestsDir = $projectRoot . '/tests/';
        if (is_dir($rootTestsDir)) {
            $executionPaths[] = $rootTestsDir;
        }

        foreach ($moduleTestPaths as $mPath) {
            $executionPaths[] = $mPath;
        }

        $argv = $_SERVER['argv'];
        $forwardArgs = [];
        $foundTest = false;
        
        foreach ($argv as $arg) {
            if ($foundTest) {
                $forwardArgs[] = $arg;
            } elseif ($arg === 'test') {
                $foundTest = true;
            }
        }

        $hasPathArg = false;
        foreach ($forwardArgs as $arg) {
            if (!str_starts_with($arg, '-')) {
                $hasPathArg = true;
                break;
            }
        }

        $commandParts = [escapeshellarg($pestBinary)];
        
        if (!$hasPathArg && !empty($executionPaths)) {
            // Auto discovery overrides
            foreach ($executionPaths as $path) {
                // Pass relative path to make pest output prettier
                $relativePath = str_replace($projectRoot . '/', '', $path);
                $commandParts[] = escapeshellarg($relativePath);
            }
        }

        foreach ($forwardArgs as $arg) {
            $commandParts[] = escapeshellarg($arg);
        }

        $commandStr = implode(' ', $commandParts);

        // Run pest in the project root
        $descriptorspec = [
            0 => ["pty"],
            1 => ["pty"],
            2 => ["pty"]
        ];

        // Instead of proc_open, passthru is usually sufficient unless output buffers
        $exitCode = 0;
        
        $currentDir = getcwd();
        chdir($projectRoot);
        passthru($commandStr, $exitCode);
        chdir($currentDir);

        return $exitCode;
    }
}
