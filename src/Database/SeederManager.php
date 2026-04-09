<?php

declare(strict_types=1);

namespace Totoglu\Console\Database;

use ProcessWire\ProcessWire;

final class SeederManager
{
    private ProcessWire $wire;

    public function __construct(ProcessWire $wire)
    {
        $this->wire = $wire;
    }

    /**
     * Get all auto-discovered seeders.
     * Searches site/seeders and site/modules/*/seeders
     *
     * @return array<string, string> Base name => Full file path
     */
    public function getAvailableSeeders(): array
    {
        $paths = [
            $this->wire->config->paths->site . 'seeders/'
        ];

        $modulesPath = $this->wire->config->paths->siteModules;
        if (is_dir($modulesPath)) {
            $matchedModules = glob($modulesPath . '*/seeders/', GLOB_ONLYDIR) ?: [];
            $paths = array_merge($paths, $matchedModules);
        }

        $seeders = [];

        foreach ($paths as $path) {
            if (is_dir($path)) {
                $files = glob($path . '*Seeder.php');
                if ($files) {
                    foreach ($files as $file) {
                        $seederName = basename($file, '.php');
                        $seeders[$seederName] = $file;
                    }
                }
            }
        }

        return $seeders;
    }
}
