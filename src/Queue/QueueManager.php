<?php

declare(strict_types=1);

namespace Totoglu\Console\Queue;

use Exception;
use PDO;
use ProcessWire\WireDatabasePDO;
use Throwable;
use Totoglu\Console\Support\FeatureDiscoverer;

/**
 * QueueManager
 *
 * Orchestrates the polling, discovery, and execution of queued jobs.
 */
final class QueueManager
{
	private const TABLE_JOBS = 'queue_jobs';
	private const TABLE_FAILED = 'failed_jobs';

	/**
	 * ProcessWire database wrapper (PDO-like API).
	 *
	 * Note: ProcessWire's $database is a WireDatabasePDO wrapper, not a native PDO instance.
	 */
	private WireDatabasePDO $db;
	private ?FeatureDiscoverer $discoverer;
	private string $jobsTable;
	private string $failedTable;

	public function __construct(?WireDatabasePDO $db = null, ?FeatureDiscoverer $discoverer = null)
	{
		$this->db = $db ?? \ProcessWire\wire('database');
		$wire = \ProcessWire\wire();
		$this->discoverer = $discoverer ?? ($wire ? new FeatureDiscoverer($wire) : null);
		$this->jobsTable = $this->db->escapeTable(self::TABLE_JOBS);
		$this->failedTable = $this->db->escapeTable(self::TABLE_FAILED);
	}

	/**
	 * Create required queue tables if they don't exist.
	 */
	public function ensureTables(): void
	{
		['engine' => $engine, 'charset' => $charset] = FeatureDiscoverer::resolveStorageTableOptions();

		$jobs = $this->jobsTable;
		$failed = $this->failedTable;

		$this->db->exec("
			CREATE TABLE IF NOT EXISTS `{$jobs}` (
				id INT UNSIGNED NOT NULL AUTO_INCREMENT,
				queue VARCHAR(255) NOT NULL,
				payload LONGTEXT NOT NULL,
				attempts INT UNSIGNED NOT NULL DEFAULT 0,
				reserved_at INT UNSIGNED DEFAULT NULL,
				available_at INT UNSIGNED NOT NULL,
				created_at INT UNSIGNED NOT NULL,
				PRIMARY KEY (id),
				INDEX queue (queue)
			) ENGINE={$engine} DEFAULT CHARSET={$charset};
		");

		$this->db->exec("
			CREATE TABLE IF NOT EXISTS `{$failed}` (
				id INT UNSIGNED NOT NULL AUTO_INCREMENT,
				queue VARCHAR(255) NOT NULL,
				payload LONGTEXT NOT NULL,
				exception LONGTEXT NOT NULL,
				failed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id)
			) ENGINE={$engine} DEFAULT CHARSET={$charset};
		");
	}

	/**
	 * Push a new job into the queue.
	 *
	 * @param array<string, mixed> $payload
	 */
	public function push(string $class, array $payload = [], string $queue = 'default', int $delay = 0): int
	{
		$data = json_encode(['class' => $class, 'data' => $payload], JSON_UNESCAPED_UNICODE);

		if ($data === false || json_last_error() !== JSON_ERROR_NONE) {
			$err = json_last_error_msg();
			throw new \RuntimeException("Failed to encode queue payload (JSON error: {$err}). Check payload for non-UTF8 strings or recursive references.");
		}

		$availableAt = time() + $delay;
		$createdAt = time();

		$jobs = $this->jobsTable;
		$stmt = $this->db->prepare("
			INSERT INTO `{$jobs}` (queue, payload, attempts, available_at, created_at)
			VALUES (:queue, :payload, 0, :available_at, :created_at)
		");

		$stmt->execute([
			':queue' => $queue,
			':payload' => $data,
			':available_at' => $availableAt,
			':created_at' => $createdAt,
		]);

		return (int) $this->db->lastInsertId();
	}

	/**
	 * Scans for queue classes ending in Queue.php globally in the project.
	 * Maps the basename (e.g. MyQueue) to the absolute PHP file path.
	 *
	 * @return array<string, string> Map of [Basename => FilePath]
	 */
	public function discoverQueues(): array
	{
		$discoverer = $this->discoverer ?? new FeatureDiscoverer(\ProcessWire\wire());
		return $discoverer->discoverFiles('queue', '*Queue.php');
	}

	/**
	 * Pops and returns the next available job, marking it as reserved. Returns null if empty.
	 *
	 * @return array<string, mixed>|null
	 */
	public function pop(string $queue = 'default'): ?array
	{
		$this->db->beginTransaction();
		$jobs = $this->jobsTable;

		try {
			$now = time();
			$expiredReservation = $now - 900;
			$stmt = $this->db->prepare("
				SELECT * FROM `{$jobs}`
				WHERE queue = :queue
				AND (reserved_at IS NULL OR reserved_at <= :expired)
				AND available_at <= :now
				ORDER BY id ASC
				LIMIT 1
				FOR UPDATE
			");

			$stmt->execute([':queue' => $queue, ':expired' => $expiredReservation, ':now' => $now]);
			$job = $stmt->fetch(PDO::FETCH_ASSOC);

			if (!$job) {
				$this->db->rollBack();
				return null;
			}

			$update = $this->db->prepare("UPDATE `{$jobs}` SET reserved_at = :now, attempts = attempts + 1 WHERE id = :id");
			$update->execute([':now' => $now, ':id' => $job['id']]);
			$job['attempts'] = ((int) ($job['attempts'] ?? 0)) + 1;
			$job['reserved_at'] = $now;

			$this->db->commit();
			return $job;
		} catch (\Throwable $e) {
			if ($this->db->inTransaction()) {
				$this->db->rollBack();
			}
			throw $e;
		}
	}

	/**
	 * Delete successfully processed job.
	 */
	public function deleteJob(int $id): void
	{
		$jobs = $this->jobsTable;
		$stmt = $this->db->prepare("DELETE FROM `{$jobs}` WHERE id = :id");
		$stmt->execute([':id' => $id]);
	}

	/**
	 * Release job back to queue for a retry (if attempts < max_tries).
	 */
	public function releaseJob(int $id, int $delaySeconds = 0): void
	{
		$jobs = $this->jobsTable;
		$availableAt = time() + $delaySeconds;
		$stmt = $this->db->prepare("UPDATE `{$jobs}` SET reserved_at = NULL, available_at = :avail WHERE id = :id");
		$stmt->execute([':avail' => $availableAt, ':id' => $id]);
	}

	/**
	 * Fail a job permanently and move it to the failed_jobs database.
	 *
	 * @param array<string, mixed> $job
	 */
	public function markFailed(array $job, Throwable $e): void
	{
		$failed = $this->failedTable;
		$this->db->beginTransaction();

		try {
			$stmt = $this->db->prepare("
				INSERT INTO `{$failed}` (queue, payload, exception)
				VALUES (:queue, :payload, :exception)
			");
			$stmt->execute([
				':queue' => $job['queue'],
				':payload' => $job['payload'],
				':exception' => (string) $e,
			]);

			$this->deleteJob((int) $job['id']);
			$this->db->commit();
		} catch (Throwable $err) {
			if ($this->db->inTransaction()) {
				$this->db->rollBack();
			}
			throw $err;
		}
	}

	/**
	 * Retrieve recent failed jobs.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getFailedJobs(int $limit = 50): array
	{
		$failed = $this->failedTable;
		$stmt = $this->db->prepare("SELECT id, queue, payload, exception, failed_at FROM `{$failed}` ORDER BY id DESC LIMIT :limit");
		$stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	/**
	 * Retrieve a single failed job by ID.
	 *
	 * @return array<string, mixed>|null
	 */
	public function getFailedJob(int $id): ?array
	{
		$failed = $this->failedTable;
		$stmt = $this->db->prepare("SELECT * FROM `{$failed}` WHERE id = :id");
		$stmt->bindValue(':id', $id, PDO::PARAM_INT);
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		return is_array($row) ? $row : null;
	}

	/**
	 * Retry a single failed job by moving it back to queue_jobs transactionally.
	 */
	public function retryJob(int $id): bool
	{
		$job = $this->getFailedJob($id);
		if ($job === null) {
			return false;
		}

		$jobs = $this->jobsTable;
		$failed = $this->failedTable;
		$now = time();

		$this->db->beginTransaction();
		try {
			$insert = $this->db->prepare("
				INSERT INTO `{$jobs}` (queue, payload, attempts, available_at, created_at)
				VALUES (:queue, :payload, 0, :available_at, :created_at)
			");
			$insert->execute([
				':queue' => $job['queue'],
				':payload' => $job['payload'],
				':available_at' => $now,
				':created_at' => $now,
			]);

			$delete = $this->db->prepare("DELETE FROM `{$failed}` WHERE id = :id");
			$delete->execute([':id' => $job['id']]);

			$this->db->commit();
			return true;
		} catch (Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

	/**
	 * Retry all failed jobs transactionally.
	 *
	 * @return int Number of jobs retried
	 */
	public function retryAllFailed(): int
	{
		$failed = $this->failedTable;
		$jobs = $this->jobsTable;

		$stmt = $this->db->query("SELECT * FROM `{$failed}` ORDER BY id ASC");
		$rows = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

		if ($rows === []) {
			return 0;
		}

		$now = time();
		$count = 0;

		$this->db->beginTransaction();
		try {
			$insert = $this->db->prepare("
				INSERT INTO `{$jobs}` (queue, payload, attempts, available_at, created_at)
				VALUES (:queue, :payload, 0, :available_at, :created_at)
			");
			$delete = $this->db->prepare("DELETE FROM `{$failed}` WHERE id = :id");

			foreach ($rows as $row) {
				$insert->execute([
					':queue' => $row['queue'],
					':payload' => $row['payload'],
					':available_at' => $now,
					':created_at' => $now,
				]);
				$delete->execute([':id' => $row['id']]);
				$count++;
			}

			$this->db->commit();
			return $count;
		} catch (Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

	/**
	 * Clear all records from the failed_jobs table.
	 *
	 * @return int Number of deleted records
	 */
	public function clearFailedJobs(): int
	{
		$failed = $this->failedTable;
		$stmt = $this->db->prepare("DELETE FROM `{$failed}`");
		$stmt->execute();
		$deleted = $stmt->rowCount();
		$this->db->exec("ALTER TABLE `{$failed}` AUTO_INCREMENT = 1");

		return $deleted;
	}

	/**
	 * Instantiates the correct Queue handler class based on the payload definition.
	 */
	public function resolveClass(string $className): Queue
	{
		if (preg_match('/^\\\\?[A-Za-z_][A-Za-z0-9_\\\\]*$/', $className) !== 1) {
			throw new Exception("Invalid queue handler class identifier [{$className}].");
		}

		$cleanName = ltrim($className, '\\');
		$candidates = [
			$cleanName,
			'Site\\Queue\\' . $cleanName,
			'ProcessWire\\' . $cleanName,
		];

		foreach ($candidates as $candidate) {
			if (class_exists($candidate) && is_subclass_of($candidate, Queue::class)) {
				$ref = new \ReflectionClass($candidate);
				if (!$ref->isAbstract()) {
					/** @var Queue $instance */
					$instance = new $candidate();
					return $instance;
				}
			}
		}

		$map = $this->discoverQueues();
		$basename = basename(str_replace('\\', '/', $cleanName));

		if (isset($map[$basename])) {
			$resolved = FeatureDiscoverer::resolveClassFromFile($map[$basename], $cleanName, Queue::class);
			if ($resolved !== null) {
				/** @var Queue $instance */
				$instance = new $resolved();
				return $instance;
			}
		}

		throw new Exception("Queue handler class [{$className}] not found, invalid, or does not extend Totoglu\\Console\\Queue\\Queue.");
	}
}
