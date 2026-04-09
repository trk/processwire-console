<?php

declare(strict_types=1);

namespace Totoglu\Console\Scheduling;

use ProcessWire\ProcessWire;

abstract class Task
{
    /**
     * @var \ProcessWire\ProcessWire
     */
    protected ProcessWire $wire;

    public function __construct(ProcessWire $wire)
    {
        $this->wire = $wire;
    }

    /**
     * Define the schedule for this task.
     * 
     * @param Event $schedule The cron event wrapper
     */
    abstract public function schedule(Event $schedule): void;

    /**
     * Execute the task logic.
     */
    abstract public function handle(): void;
}
