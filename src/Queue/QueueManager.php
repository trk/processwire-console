<?php

declare(strict_types=1);

namespace Totoglu\Console\Queue;

use PDO;
use Exception;
use Throwable;
use ProcessWire\WireDatabasePDO;

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
    private string $sitePath;
    private string $jobsTable;
    private string $failedTable;

    public function __construct()
    {
        $this->db = \ProcessWire\wire('database');
        $this->sitePath = \ProcessWire\wire('config')->paths->site;
        $this->jobsTable = $this->db->escapeTable(self::TABLE_JOBS);
        $this->failedTable = $this->db->escapeTable(self::TABLE_FAILED);
    }

    /**
     * Create required queue tables if they don't exist.
     */
    public function ensureTables(): void
    {
        $config = \ProcessWire\wire('config');
        $engine = $config->dbEngine ?: 'InnoDB';
        $charset = $config->dbCharset ?: 'utf8mb4';
        $allowedEngines = ['InnoDB', 'MyISAM', 'Aria', 'MEMORY'];
        if (!in_array($engine, $allowedEngines, true)) {
            $engine = 'InnoDB';
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $charset)) {
            $charset = 'utf8mb4';
        }

        $jobs = $this->jobsTable;
        $failed = $this->failedTable;

        // Check and create queue_jobs table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS `{$jobs}` (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                queue VARCHAR(255) NOT NULL,
                payload LONGTEXT NOT NULL,
                attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
                reserved_at INT UNSIGNED DEFAULT NULL,
                available_at INT UNSIGNED NOT NULL,
                created_at INT UNSIGNED NOT NULL,
                PRIMARY KEY (id),
                INDEX queue (queue)
            ) ENGINE={$engine} DEFAULT CHARSET={$charset};
        ");

        // Check and create failed_jobs table
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
            ':created_at' => $createdAt
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Scans for queue classes ending in Queue.php globally in the project.
     * Maps the basename (e.g. MyQueue) to the absolute PHP file path.
     * 
     * @return array<string, string> Map of [Basename => FilePath]
     */
    public function discoverQueues(): array
    {
        $discoverer = new \Totoglu\Console\Support\FeatureDiscoverer(\ProcessWire\wire());
        return $discoverer->discoverFiles('queue', '*Queue.php');
    }

    /**
     * Pops and returns the next available job, marking it as reserved. Returns null if empty.
     */
    public function pop(string $queue = 'default'): ?array
    {
        $this->db->beginTransaction();
        $jobs = $this->jobsTable;

        try {
            $now = time();
            $stmt = $this->db->prepare("
                SELECT * FROM `{$jobs}`
                WHERE queue = :queue
                  AND reserved_at IS NULL
                  AND available_at <= :now
                ORDER BY id ASC
                LIMIT 1
                FOR UPDATE
            ");

            $stmt->execute([':queue' => $queue, ':now' => $now]);
            $job = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$job) {
                $this->db->rollBack();
                return null;
            }

            // Mark as reserved and increment attempts
            $update = $this->db->prepare("UPDATE `{$jobs}` SET reserved_at = :now, attempts = attempts + 1 WHERE id = :id");
            $update->execute([':now' => $now, ':id' => $job['id']]);

            $this->db->commit();
            return $job;
        } catch (\Exception $e) {
            $this->db->rollBack();
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
     * Release job back to queue for a retry (if attempts < max_tries)
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
     */
    public function markFailed(array $job, Throwable $e): void
    {
        $failed = $this->failedTable;
        // 1. Move to failed_jobs
        $stmt = $this->db->prepare("
            INSERT INTO `{$failed}` (queue, payload, exception)
            VALUES (:queue, :payload, :exception)
        ");
        $stmt->execute([
            ':queue' => $job['queue'],
            ':payload' => $job['payload'],
            ':exception' => (string)$e
        ]);

        // 2. Delete from active queue
        $this->deleteJob((int)$job['id']);
    }

    /**
     * Instantiates the correct Queue handler class based on the payload definition.
     */
    public function resolveClass(string $className): Queue
    {
        // Direct instantiation if strictly autoloaded / FQCN present
        if (class_exists($className) && is_subclass_of($className, Queue::class)) {
            return new $className();
        }

        // Try auto-discovery via file map
        $map = $this->discoverQueues();
        $basename = basename(str_replace('\\', '/', $className));

        if (isset($map[$basename])) {
            require_once $map[$basename];

            if (class_exists($className) && is_subclass_of($className, Queue::class)) {
                return new $className();
            }

            if (class_exists($basename) && is_subclass_of($basename, Queue::class)) {
                return new $basename();
            }
        }

        throw new Exception("Queue handler class [{$className}] not found, invalid, or does not extend Totoglu\Console\Queue\Queue.");
    }
}
