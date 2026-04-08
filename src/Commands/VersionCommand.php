<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function Laravel\Prompts\table;

final class VersionCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('version')
            ->setDescription('Show version information for ProcessWire, Console, and Boost (if installed).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rows = [];

        // 1. ProcessWire Core Version
        $pwVersion = 'Unknown';
        if (class_exists(\ProcessWire\ProcessWire::class)) {
            $pwVersion = \ProcessWire\ProcessWire::versionMajor . '.' . 
                       \ProcessWire\ProcessWire::versionMinor . '.' . 
                       \ProcessWire\ProcessWire::versionRevision;
            
            $suffix = \ProcessWire\ProcessWire::versionSuffix;
            if ($suffix) {
                $pwVersion .= '-' . $suffix;
            }
        }
        $rows[] = ['processwire/core', $pwVersion];

        // 2. processwire-console
        $rows[] = ['processwire-console', $this->getVersion('trk/processwire-console')];

        // 3. processwire-boost (if installed)
        $boostVersion = $this->getVersion('trk/processwire-boost');
        if ($boostVersion !== 'Unknown') {
            $rows[] = ['processwire-boost', $boostVersion];
        }
        
        table(['Package', 'Version'], $rows);
        
        return Command::SUCCESS;
    }

    private function getVersion(string $package): string
    {
        try {
            if (class_exists(\Composer\InstalledVersions::class)) {
                /** @var class-string $iv */
                $iv = \Composer\InstalledVersions::class;
                if (method_exists($iv, 'isInstalled') && $iv::isInstalled($package)) {
                    return (string)($iv::getPrettyVersion($package) ?? $iv::getVersion($package) ?? 'Unknown');
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        if (function_exists('\ProcessWire\wire')) {
            $wireConfig = \ProcessWire\wire('config');
            if ($wireConfig) {
                $root = $wireConfig->paths->root;
                $path = $root . 'vendor/composer/installed.json';
                if (is_file($path)) {
                    try {
                        $json = json_decode((string)file_get_contents($path), true);
                        $packages = $json['packages'] ?? $json ?? [];
                        if (is_array($packages)) {
                            foreach ($packages as $p) {
                                if (($p['name'] ?? '') === $package) {
                                    return (string)($p['pretty_version'] ?? $p['version'] ?? 'Unknown');
                                }
                            }
                        }
                    } catch (\Throwable $e) {
                        // ignore
                    }
                }
            }
        }
        
        return 'Unknown';
    }
}
