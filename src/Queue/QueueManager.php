<?php

declare(strict_types=1);

namespace Totoglu\Console\Queue;

use PDO;
use Exception;
use Throwable;

/**
 * QueueManager
 * 
 * Orchestrates the polling, discovery, and execution of queued jobs.
 */
final class QueueManager
{
    private PDO $db;
    private string $sitePath;

    public function __construct()
    {
        $this->db = \ProcessWire\wire('database');
        $this->sitePath = \ProcessWire\wire('config')->paths->site;
    }

    /**
     * Create required queue tables if they don't exist.
     */
    public function ensureTables(): void
    {
        // Check and create queue_jobs table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS queue_jobs (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                queue VARCHAR(255) NOT NULL,
                payload LONGTEXT NOT NULL,
                attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
                reserved_at INT UNSIGNED DEFAULT NULL,
                available_at INT UNSIGNED NOT NULL,
                created_at INT UNSIGNED NOT NULL,
                PRIMARY KEY (id),
                INDEX queue (queue)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Check and create failed_jobs table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS failed_jobs (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                queue VARCHAR(255) NOT NULL,
                payload LONGTEXT NOT NULL,
                exception LONGTEXT NOT NULL,
                failed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    /**
     * Push a new job into the queue.
     */
    public function push(string $class, array $payload = [], string $queue = 'default', int $delay = 0): int
    {
        $data = json_encode(['class' => $class, 'data' => $payload], JSON_UNESCAPED_UNICODE);
        $availableAt = time() + $delay;
        $createdAt = time();

        $stmt = $this->db->prepare("
            INSERT INTO queue_jobs (queue, payload, attempts, available_at, created_at)
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
        $classes = [];
        
        // 1. Check site/queue/
        $siteQueuePath = $this->sitePath . 'queue/';
        if (is_dir($siteQueuePath)) {
            $files = glob($siteQueuePath . '*Queue.php');
            if ($files !== false) {
                foreach ($files as $file) {
                    $classes[basename($file, '.php')] = $file;
                }
            }
        }
        
        // 2. Check site/modules/*/queue/
        $modulesPath = $this->sitePath . 'modules/';
        if (is_dir($modulesPath)) {
            $dirs = glob($modulesPath . '*', GLOB_ONLYDIR);
            if ($dirs !== false) {
                foreach ($dirs as $dir) {
                    $modQueuePath = $dir . '/queue/';
                    if (is_dir($modQueuePath)) {
                        $files = glob($modQueuePath . '*Queue.php');
                        if ($files !== false) {
                            foreach ($files as $file) {
                                $classes[basename($file, '.php')] = $file;
                            }
                        }
                    }
                }
            }
        }
        
        return $classes;
    }

    /**
     * Pops and returns the next available job, marking it as reserved. Returns null if empty.
     */
    public function pop(string $queue = 'default'): ?array
    {
        $this->db->beginTransaction();
        
        try {
            $now = time();
            $stmt = $this->db->prepare("
                SELECT * FROM queue_jobs 
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
            $update = $this->db->prepare("UPDATE queue_jobs SET reserved_at = :now, attempts = attempts + 1 WHERE id = :id");
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
        $stmt = $this->db->prepare("DELETE FROM queue_jobs WHERE id = :id");
        $stmt->execute([':id' => $id]);
    }

    /**
     * Release job back to queue for a retry (if attempts < max_tries)
     */
    public function releaseJob(int $id, int $delaySeconds = 0): void
    {
        $availableAt = time() + $delaySeconds;
        $stmt = $this->db->prepare("UPDATE queue_jobs SET reserved_at = NULL, available_at = :avail WHERE id = :id");
        $stmt->execute([':avail' => $availableAt, ':id' => $id]);
    }

    /**
     * Fail a job permanently and move it to the failed_jobs database.
     */
    public function markFailed(array $job, Throwable $e): void
    {
        // 1. Move to failed_jobs
        $stmt = $this->db->prepare("
            INSERT INTO failed_jobs (queue, payload, exception)
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
