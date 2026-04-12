<?php

declare(strict_types=1);

namespace Totoglu\Console\Support;

use ProcessWire\ProcessWire;

/**
 * FeatureDiscoverer
 * 
 * Centralized service to discover project features (commands, seeders, etc.)
 * mapping them intelligently between the root site/ path and active module paths.
 */
final class FeatureDiscoverer
{
    private ProcessWire $wire;

    public function __construct(ProcessWire $wire)
    {
        $this->wire = $wire;
    }

    /**
     * Get valid feature directory paths
     * 
     * @param string $featureFolderName Subdirectory name (e.g., 'commands', 'seeders')
     * @return array<string> Valid absolute directory paths
     */
    public function getFeaturePaths(string $featureFolderName): array
    {
        $paths = [];
        
        // 1. Root site feature path
        $sitePath = $this->wire->config->paths->site . trim($featureFolderName, '/') . '/';
        if (is_dir($sitePath)) {
            $paths[] = $sitePath;
        }

        // 2. Module feature paths (Only for installed modules)
        $modulesPath = $this->wire->config->paths->siteModules;
        if (is_dir($modulesPath)) {
            $matchedDirs = glob($modulesPath . '*/' . trim($featureFolderName, '/') . '/', GLOB_ONLYDIR);
            if ($matchedDirs !== false) {
                foreach ($matchedDirs as $dir) {
                    $parts = explode('/', trim($dir, '/'));
                    array_pop($parts); // remove featureFolderName
                    $moduleName = array_pop($parts); // get module name
                    
                    // Critical: only scan if module is installed and active
                    if ($this->wire->modules->isInstalled($moduleName)) {
                        $paths[] = $dir;
                    }
                }
            }
        }

        return $paths;
    }

    /**
     * Discover files for a given feature folder matching a pattern.
     * 
     * @param string $featureFolderName e.g., 'seeders'
     * @param string $pattern Glob pattern within the folder (e.g., '*Seeder.php')
     * @param string|null $stripExtension If provided, strips this extension from keys (e.g., '.php')
     * @return array<string, string> Map of base names to their absolute paths
     */
    public function discoverFiles(string $featureFolderName, string $pattern = '*.php', ?string $stripExtension = '.php'): array
    {
        $paths = $this->getFeaturePaths($featureFolderName);
        $filesMap = [];

        foreach ($paths as $path) {
            $foundFiles = glob($path . ltrim($pattern, '/'));
            if ($foundFiles !== false) {
                foreach ($foundFiles as $file) {
                    $basename = basename($file);
                    if ($stripExtension !== null) {
                        $basename = basename($file, $stripExtension);
                    }
                    // In case of duplicate basenames across modules, the last loaded overwrites.
                    $filesMap[$basename] = $file;
                }
            }
        }

        ksort($filesMap); // Sort alphabetically by base name primarily (good for migrations, consistency)

        return $filesMap;
    }
}
