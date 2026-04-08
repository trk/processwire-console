<?php

declare(strict_types=1);

namespace Totoglu\Console\Migration;

/**
 * MigrationRepository — Database tracking layer for migrations.
 *
 * Manages the `wire_migrations` table: creation, insertion, deletion,
 * querying applied migrations, and batch number management.
 */
final class MigrationRepository
{
    private const TABLE = 'wire_migrations';

    /**
     * Ensure the migrations table exists. Auto-creates if missing.
     */
    public function ensureTable(): void
    {
        $db = \ProcessWire\wire('database');
        $config = \ProcessWire\wire('config');
        $table = $db->escapeTable(self::TABLE);

        $engine = $config->dbEngine ?: 'InnoDB';
        $charset = $config->dbCharset ?: 'utf8mb4';

        $db->exec("
            CREATE TABLE IF NOT EXISTS `{$table}` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `migration` VARCHAR(255) NOT NULL UNIQUE,
                `batch` INT UNSIGNED NOT NULL,
                `applied_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE={$engine} DEFAULT CHARSET={$charset}
        ");
    }

    /**
     * Check if the migrations table exists.
     */
    public function tableExists(): bool
    {
        $db = \ProcessWire\wire('database');
        $table = $db->escapeTable(self::TABLE);

        try {
            $stmt = $db->query("SELECT 1 FROM `{$table}` LIMIT 1");
            $stmt->closeCursor();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Drop the migrations table entirely.
     */
    public function dropTable(): void
    {
        $db = \ProcessWire\wire('database');
        $table = $db->escapeTable(self::TABLE);
        $db->exec("DROP TABLE IF EXISTS `{$table}`");
    }

    /**
     * Get all applied migration names, ordered chronologically.
     *
     * @return array<int, array{migration: string, batch: int}>
     */
    public function getApplied(): array
    {
        $db = \ProcessWire\wire('database');
        $table = $db->escapeTable(self::TABLE);

        $stmt = $db->query("SELECT `migration`, `batch` FROM `{$table}` ORDER BY `id` ASC");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get the list of applied migration filenames (name only, no batch info).
     *
     * @return string[]
     */
    public function getAppliedNames(): array
    {
        return array_column($this->getApplied(), 'migration');
    }

    /**
     * Get the last batch number.
     */
    public function getLastBatchNumber(): int
    {
        $db = \ProcessWire\wire('database');
        $table = $db->escapeTable(self::TABLE);

        $stmt = $db->query("SELECT MAX(`batch`) AS `max_batch` FROM `{$table}`");
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return (int)($row['max_batch'] ?? 0);
    }

    /**
     * Get the next batch number.
     */
    public function getNextBatchNumber(): int
    {
        return $this->getLastBatchNumber() + 1;
    }

    /**
     * Get all migrations from a specific batch, in reverse order (for rollback).
     *
     * @return array<int, array{migration: string, batch: int}>
     */
    public function getMigrationsForBatch(int $batch): array
    {
        $db = \ProcessWire\wire('database');
        $table = $db->escapeTable(self::TABLE);

        $stmt = $db->prepare("SELECT `migration`, `batch` FROM `{$table}` WHERE `batch` = :batch ORDER BY `id` DESC");
        $stmt->bindValue(':batch', $batch, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get the last N applied migrations in reverse order (for step-based rollback).
     *
     * @return array<int, array{migration: string, batch: int}>
     */
    public function getLastMigrations(int $steps): array
    {
        $db = \ProcessWire\wire('database');
        $table = $db->escapeTable(self::TABLE);

        $stmt = $db->prepare("SELECT `migration`, `batch` FROM `{$table}` ORDER BY `id` DESC LIMIT :steps");
        $stmt->bindValue(':steps', $steps, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Record a migration as applied.
     */
    public function log(string $migration, int $batch): void
    {
        $db = \ProcessWire\wire('database');
        $table = $db->escapeTable(self::TABLE);

        $stmt = $db->prepare("INSERT INTO `{$table}` (`migration`, `batch`) VALUES (:migration, :batch)");
        $stmt->bindValue(':migration', $migration);
        $stmt->bindValue(':batch', $batch, \PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Remove a migration record (for rollback).
     */
    public function delete(string $migration): void
    {
        $db = \ProcessWire\wire('database');
        $table = $db->escapeTable(self::TABLE);

        $stmt = $db->prepare("DELETE FROM `{$table}` WHERE `migration` = :migration");
        $stmt->bindValue(':migration', $migration);
        $stmt->execute();
    }
}
