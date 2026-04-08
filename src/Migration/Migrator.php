<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Console\Migration;

/**
 * Migrator — Core migration engine.
 *
 * Discovers migration files, determines pending/applied status,
 * executes up/down methods, and manages batch numbering.
 */
final class Migrator
{
    private MigrationRepository $repository;

    public function __construct(MigrationRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Get the migration directory path (site/migrations/).
     */
    public function getMigrationsPath(): string
    {
        $config = \ProcessWire\wire('config');
        return $config->paths->site . 'migrations/';
    }

    /**
     * Ensure the migrations directory exists.
     */
    public function ensureMigrationsDirectory(): void
    {
        $path = $this->getMigrationsPath();
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    /**
     * Discover all migration files in the migrations directory.
     *
     * @return string[] Sorted list of migration file basenames (without path)
     */
    public function discoverFiles(): array
    {
        $path = $this->getMigrationsPath();
        if (!is_dir($path)) {
            return [];
        }

        $files = glob($path . '*.php');
        if ($files === false || empty($files)) {
            return [];
        }

        sort($files);

        return array_map('basename', $files);
    }

    /**
     * Get pending migrations (not yet applied).
     *
     * @return string[] Basenames of pending migration files
     */
    public function getPending(): array
    {
        $all = $this->discoverFiles();
        $applied = $this->repository->getAppliedNames();

        return array_values(array_diff($all, $applied));
    }

    /**
     * Run all pending migrations (or up to $steps).
     *
     * @return array{applied: string[], errors: string[]}
     */
    public function runPending(int $steps = 0): array
    {
        $this->repository->ensureTable();

        $pending = $this->getPending();
        if ($steps > 0) {
            $pending = array_slice($pending, 0, $steps);
        }

        $batch = $this->repository->getNextBatchNumber();
        $applied = [];
        $errors = [];

        foreach ($pending as $file) {
            try {
                $this->runUp($file);
                $this->repository->log($file, $batch);
                $applied[] = $file;
            } catch (\Throwable $e) {
                $errors[] = "{$file}: {$e->getMessage()}";
                break; // Stop on first error
            }
        }

        return ['applied' => $applied, 'errors' => $errors];
    }

    /**
     * Rollback the last batch.
     *
     * @return array{rolledBack: string[], errors: string[]}
     */
    public function rollbackLastBatch(): array
    {
        $lastBatch = $this->repository->getLastBatchNumber();
        if ($lastBatch === 0) {
            return ['rolledBack' => [], 'errors' => []];
        }

        $migrations = $this->repository->getMigrationsForBatch($lastBatch);
        return $this->rollbackMigrations($migrations);
    }

    /**
     * Rollback the last N migrations (step-based).
     *
     * @return array{rolledBack: string[], errors: string[]}
     */
    public function rollbackSteps(int $steps): array
    {
        $migrations = $this->repository->getLastMigrations($steps);
        return $this->rollbackMigrations($migrations);
    }

    /**
     * Rollback ALL applied migrations (reset).
     *
     * @return array{rolledBack: string[], errors: string[]}
     */
    public function reset(): array
    {
        $applied = $this->repository->getApplied();
        // Reverse for rollback order (most recent first)
        $reversed = array_reverse($applied);

        return $this->rollbackMigrations($reversed);
    }

    /**
     * Get the full status of all migrations.
     *
     * @return array<int, array{name: string, status: string, batch: int|null}>
     */
    public function getStatus(): array
    {
        $this->repository->ensureTable();

        $all = $this->discoverFiles();
        $applied = $this->repository->getApplied();
        $appliedMap = [];
        foreach ($applied as $row) {
            $appliedMap[$row['migration']] = (int)$row['batch'];
        }

        $status = [];
        foreach ($all as $file) {
            $batch = $appliedMap[$file] ?? null;
            $status[] = [
                'name' => $file,
                'status' => $batch !== null ? 'applied' : 'pending',
                'batch' => $batch,
            ];
        }

        return $status;
    }

    /**
     * Execute the up() method of a migration file.
     */
    private function runUp(string $file): void
    {
        $instance = $this->resolve($file);
        if (!method_exists($instance, 'up')) {
            throw new \RuntimeException("Migration '{$file}' does not have an up() method.");
        }
        $instance->up();
    }

    /**
     * Execute the down() method of a migration file.
     */
    private function runDown(string $file): void
    {
        $instance = $this->resolve($file);
        if (!method_exists($instance, 'down')) {
            throw new \RuntimeException("Migration '{$file}' does not have a down() method.");
        }
        $instance->down();
    }

    /**
     * Resolve a migration file into an anonymous class instance.
     */
    private function resolve(string $file): object
    {
        $path = $this->getMigrationsPath() . $file;
        if (!is_file($path)) {
            throw new \RuntimeException("Migration file not found: {$path}");
        }

        $instance = require $path;

        if (!is_object($instance)) {
            throw new \RuntimeException("Migration '{$file}' must return an anonymous class instance.");
        }

        return $instance;
    }

    /**
     * Rollback a set of migrations.
     *
     * @param array<int, array{migration: string, batch: int}> $migrations
     * @return array{rolledBack: string[], errors: string[]}
     */
    private function rollbackMigrations(array $migrations): array
    {
        $rolledBack = [];
        $errors = [];

        foreach ($migrations as $row) {
            $file = $row['migration'];
            try {
                $this->runDown($file);
                $this->repository->delete($file);
                $rolledBack[] = $file;
            } catch (\Throwable $e) {
                $errors[] = "{$file}: {$e->getMessage()}";
                break;
            }
        }

        return ['rolledBack' => $rolledBack, 'errors' => $errors];
    }
}
