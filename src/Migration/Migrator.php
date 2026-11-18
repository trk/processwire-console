<?php

declare(strict_types=1);

namespace Totoglu\Console\Migration;

use PDO;
use ProcessWire\WireDatabasePDO;
use Totoglu\Console\Support\FeatureDiscoverer;

/**
 * Migrator — Core migration engine.
 *
 * Discovers migration files, manages the `wire_migrations` tracking table,
 * determines pending/applied status, executes up/down methods, and manages batch numbering.
 */
final class Migrator
{
	private const TABLE = 'wire_migrations';

	private WireDatabasePDO $db;
	private ?FeatureDiscoverer $discoverer;
	private string $table;

	/**
	 * Map of basename => full path
	 * @var array<string, string>
	 */
	private array $filesMap = [];
	private bool $filesDiscovered = false;

	public function __construct(?WireDatabasePDO $db = null, ?FeatureDiscoverer $discoverer = null)
	{
		$this->db = $db ?? \ProcessWire\wire('database');
		$wire = \ProcessWire\wire();
		$this->discoverer = $discoverer ?? ($wire ? new FeatureDiscoverer($wire) : null);
		$this->table = $this->db->escapeTable(self::TABLE);
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
	 * Resolve the map of migrations natively.
	 *
	 * @return array<string, string>
	 */
	private function getFilesMap(): array
	{
		if (!$this->filesDiscovered) {
			$discoverer = $this->discoverer ?? new FeatureDiscoverer(\ProcessWire\wire());
			$this->filesMap = $discoverer->discoverFiles('migrations', '*.php', null);
			$this->filesDiscovered = true;
		}
		return $this->filesMap;
	}

	/**
	 * Discover all migration files in the migrations directory and modules.
	 *
	 * @return string[] Sorted list of migration file basenames (without path)
	 */
	public function discoverFiles(): array
	{
		return array_keys($this->getFilesMap());
	}

	/**
	 * Get pending migrations (not yet applied).
	 *
	 * @return string[] Basenames of pending migration files
	 */
	public function getPending(): array
	{
		$all = $this->discoverFiles();
		$applied = $this->getAppliedNames();

		return array_values(array_diff($all, $applied));
	}

	/**
	 * Get migration filenames that would be rolled back next.
	 *
	 * @return string[]
	 */
	public function getPendingRollback(int $steps = 0): array
	{
		if (!$this->tableExists()) {
			return [];
		}

		if ($steps > 0) {
			$toRollback = $this->getLastMigrations($steps);
		} else {
			$lastBatch = $this->getLastBatchNumber();
			$toRollback = $lastBatch > 0 ? $this->getMigrationsForBatch($lastBatch) : [];
		}

		return array_column($toRollback, 'migration');
	}

	/**
	 * Ensure the migration tracking table is installed.
	 *
	 * @return bool True if newly created, false if it already existed
	 */
	public function install(): bool
	{
		if ($this->tableExists()) {
			return false;
		}

		$this->ensureTable();
		return true;
	}

	/**
	 * Drop the migration tracking table, recreate it, and run all migrations from scratch.
	 *
	 * @return array{applied: string[], errors: string[]}
	 */
	public function fresh(): array
	{
		$this->dropTable();
		$this->ensureTable();
		$this->filesDiscovered = false;

		return $this->runPending();
	}

	/**
	 * Run all pending migrations (or up to $steps).
	 *
	 * @return array{applied: string[], errors: string[]}
	 */
	public function runPending(int $steps = 0): array
	{
		$this->ensureTable();

		$pending = $this->getPending();
		if ($steps > 0) {
			$pending = array_slice($pending, 0, $steps);
		}

		$batch = $this->getNextBatchNumber();
		$applied = [];
		$errors = [];

		foreach ($pending as $file) {
			try {
				$this->runUp($file);
				$this->log($file, $batch);
				$applied[] = $file;
			} catch (\Throwable $e) {
				$errors[] = "{$file}: {$e->getMessage()}";
				break;
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
		$lastBatch = $this->getLastBatchNumber();
		if ($lastBatch === 0) {
			return ['rolledBack' => [], 'errors' => []];
		}

		$migrations = $this->getMigrationsForBatch($lastBatch);
		return $this->rollbackMigrations($migrations);
	}

	/**
	 * Rollback the last N migrations (step-based).
	 *
	 * @return array{rolledBack: string[], errors: string[]}
	 */
	public function rollbackSteps(int $steps): array
	{
		$migrations = $this->getLastMigrations($steps);
		return $this->rollbackMigrations($migrations);
	}

	/**
	 * Rollback ALL applied migrations (reset).
	 *
	 * @return array{rolledBack: string[], errors: string[]}
	 */
	public function reset(): array
	{
		$applied = $this->getApplied();
		$reversed = array_reverse($applied);

		return $this->rollbackMigrations($reversed);
	}

	/**
	 * Return applied migration names in exact reverse execution order (matching reset()).
	 *
	 * @return list<string>
	 */
	public function getResetOrder(): array
	{
		return array_values(array_reverse($this->getAppliedNames()));
	}

	/**
	 * Get the full status of all migrations.
	 *
	 * @return array<int, array{name: string, status: string, batch: int|null}>
	 */
	public function getStatus(): array
	{
		$all = $this->discoverFiles();
		$applied = $this->tableExists() ? $this->getApplied() : [];
		$appliedMap = [];
		foreach ($applied as $row) {
			$appliedMap[$row['migration']] = (int) $row['batch'];
		}

		$status = [];
		$seenOnDisk = [];
		foreach ($all as $file) {
			$seenOnDisk[$file] = true;
			$batch = $appliedMap[$file] ?? null;
			$status[] = [
				'name' => $file,
				'status' => $batch !== null ? 'applied' : 'pending',
				'batch' => $batch,
			];
		}

		foreach ($appliedMap as $appliedFile => $batch) {
			if (!isset($seenOnDisk[$appliedFile])) {
				$status[] = [
					'name' => $appliedFile,
					'status' => 'missing',
					'batch' => $batch,
				];
			}
		}

		return $status;
	}

	private function ensureTable(): void
	{
		$table = $this->table;
		['engine' => $engine, 'charset' => $charset] = FeatureDiscoverer::resolveStorageTableOptions();

		$this->db->exec("
			CREATE TABLE IF NOT EXISTS `{$table}` (
				`id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
				`migration` VARCHAR(255) NOT NULL UNIQUE,
				`batch` INT UNSIGNED NOT NULL,
				`applied_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
			) ENGINE={$engine} DEFAULT CHARSET={$charset}
		");
	}

	private function tableExists(): bool
	{
		$table = $this->table;

		try {
			$stmt = $this->db->query("SELECT 1 FROM `{$table}` LIMIT 1");
			if ($stmt === false) {
				return false;
			}
			$stmt->closeCursor();
			return true;
		} catch (\Throwable) {
			return false;
		}
	}

	private function dropTable(): void
	{
		$table = $this->table;
		$this->db->exec("DROP TABLE IF EXISTS `{$table}`");
	}

	/**
	 * @return array<int, array{migration: string, batch: int}>
	 */
	private function getApplied(): array
	{
		if (!$this->tableExists()) {
			return [];
		}
		$table = $this->table;
		$stmt = $this->db->query("SELECT `migration`, `batch` FROM `{$table}` ORDER BY `id` ASC");
		return $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
	}

	/**
	 * @return string[]
	 */
	private function getAppliedNames(): array
	{
		return array_column($this->getApplied(), 'migration');
	}

	private function getLastBatchNumber(): int
	{
		if (!$this->tableExists()) {
			return 0;
		}
		$table = $this->table;
		$stmt = $this->db->query("SELECT MAX(`batch`) AS `max_batch` FROM `{$table}`");
		$row = $stmt !== false ? $stmt->fetch(PDO::FETCH_ASSOC) : false;

		return is_array($row) ? (int) ($row['max_batch'] ?? 0) : 0;
	}

	private function getNextBatchNumber(): int
	{
		return $this->getLastBatchNumber() + 1;
	}

	/**
	 * @return array<int, array{migration: string, batch: int}>
	 */
	private function getMigrationsForBatch(int $batch): array
	{
		if (!$this->tableExists()) {
			return [];
		}
		$table = $this->table;
		$stmt = $this->db->prepare("SELECT `migration`, `batch` FROM `{$table}` WHERE `batch` = :batch ORDER BY `id` DESC");
		$stmt->bindValue(':batch', $batch, PDO::PARAM_INT);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	/**
	 * @return array<int, array{migration: string, batch: int}>
	 */
	private function getLastMigrations(int $steps): array
	{
		if (!$this->tableExists()) {
			return [];
		}
		$table = $this->table;
		$stmt = $this->db->prepare("SELECT `migration`, `batch` FROM `{$table}` ORDER BY `id` DESC LIMIT :steps");
		$stmt->bindValue(':steps', $steps, PDO::PARAM_INT);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	private function log(string $migration, int $batch): void
	{
		$table = $this->table;
		$stmt = $this->db->prepare("INSERT INTO `{$table}` (`migration`, `batch`) VALUES (:migration, :batch)");
		$stmt->bindValue(':migration', $migration);
		$stmt->bindValue(':batch', $batch, PDO::PARAM_INT);
		$stmt->execute();
	}

	private function delete(string $migration): void
	{
		$table = $this->table;
		$stmt = $this->db->prepare("DELETE FROM `{$table}` WHERE `migration` = :migration");
		$stmt->bindValue(':migration', $migration);
		$stmt->execute();
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
		$map = $this->getFilesMap();
		if (!isset($map[$file]) || !is_file($map[$file])) {
			throw new \RuntimeException("Migration file not found: {$file}");
		}

		$instance = require $map[$file];

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
				$this->delete($file);
				$rolledBack[] = $file;
			} catch (\Throwable $e) {
				$errors[] = "{$file}: {$e->getMessage()}";
				break;
			}
		}

		return ['rolledBack' => $rolledBack, 'errors' => $errors];
	}
}
