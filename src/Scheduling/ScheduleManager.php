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
     * Searches site/schedule and site/modules/[name]/schedule
     *
     * @return array<string, string> Base name => Full file path
     */
    public function getAvailableTasks(): array
    {
        $discoverer = new \Totoglu\Console\Support\FeatureDiscoverer($this->wire);
        return $discoverer->discoverFiles('schedule', '*Task.php');
    }
}
