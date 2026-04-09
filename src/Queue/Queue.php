<?php

declare(strict_types=1);

namespace Totoglu\Console\Queue;

/**
 * Base Queue Contract
 * 
 * Custom queues in site/queue/ or modules should extend this class.
 */
abstract class Queue
{
    /**
     * Push a job onto the queue.
     *
     * @param string $queueClass The FQCN or the basename of the target Queue class (e.g. 'SendEmailQueue')
     * @param array<string, mixed> $payload Optional payload for the job
     * @param string $queueName The queue channel, default 'default'
     * @param int $delay Number of seconds to delay execution
     */
    public static function push(string $queueClass, array $payload = [], string $queueName = 'default', int $delay = 0): int
    {
        return (new QueueManager())->push($queueClass, $payload, $queueName, $delay);
    }

    /**
     * The method that will be executed when the job is processed.
     * 
     * @param array<string, mixed> $payload The data passed into push()
     */
    abstract public function handle(array $payload): void;
}
