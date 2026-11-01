<?php

declare(strict_types=1);

namespace Totoglu\Console\Scheduling;

use ProcessWire\ProcessWire;

abstract class Task
{
	public function __construct(protected ProcessWire $wire)
	{
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
