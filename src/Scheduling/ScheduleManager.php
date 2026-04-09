<?php

declare(strict_types=1);

namespace Totoglu\Console\Scheduling;

use ProcessWire\ProcessWire;

final class ScheduleManager
{
    private ProcessWire $wire;

    public function __construct(ProcessWire $wire)
    {
        $this->wire = $wire;
    }

    /**
     * Get all auto-discovered scheduled tasks.
     * Searches site/schedule and site/modules/*/schedule
     *
     * @return array<string, string> Base name => Full file path
     */
    public function getAvailableTasks(): array
    {
        $paths = [
            $this->wire->config->paths->site . 'schedule/'
        ];

        $modulesPath = $this->wire->config->paths->siteModules;
        if (is_dir($modulesPath)) {
            $matchedModules = glob($modulesPath . '*/schedule/', GLOB_ONLYDIR) ?: [];
            $paths = array_merge($paths, $matchedModules);
        }

        $tasks = [];

        foreach ($paths as $path) {
            if (is_dir($path)) {
                $files = glob($path . '*Task.php');
                if ($files) {
                    foreach ($files as $file) {
                        $taskName = basename($file, '.php');
                        $tasks[$taskName] = $file;
                    }
                }
            }
        }

        return $tasks;
    }
}
