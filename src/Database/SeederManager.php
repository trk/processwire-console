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
     * Searches site/seeders and site/modules/[name]/seeders
     *
     * @return array<string, string> Base name => Full file path
     */
    public function getAvailableSeeders(): array
    {
        $discoverer = new \Totoglu\Console\Support\FeatureDiscoverer($this->wire);
        return $discoverer->discoverFiles('seeders', '*Seeder.php');

    }
}
