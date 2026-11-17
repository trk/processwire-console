<?php

declare(strict_types=1);

namespace Totoglu\Console\Scheduling;

use Cron\CronExpression;

class Event
{
	protected string $expression = '* * * * *';

	protected string $timezone;

	public function __construct(?string $timezone = null)
	{
		$this->timezone = ($timezone !== null && $timezone !== '') ? $timezone : date_default_timezone_get();
	}

	/**
	 * Get the defined cron expression.
	 */
	public function getExpression(): string
	{
		return $this->expression;
	}

	/**
	 * Set a raw cron expression.
	 */
	public function cron(string $expression): self
	{
		$this->expression = $expression;
		return $this;
	}

	/**
	 * Run the task every minute.
	 */
	public function everyMinute(): self
	{
		return $this->cron('* * * * *');
	}

	/**
	 * Run the task every five minutes.
	 */
	public function everyFiveMinutes(): self
	{
		return $this->cron('*/5 * * * *');
	}

	/**
	 * Run the task every hour.
	 */
	public function hourly(): self
	{
		return $this->cron('0 * * * *');
	}

	/**
	 * Run the task daily at midnight.
	 */
	public function daily(): self
	{
		return $this->cron('0 0 * * *');
	}

	/**
	 * Run the task daily at a specific time (HH:MM).
	 */
	public function dailyAt(string $time): self
	{
		$segments = explode(':', trim($time));

		$hour = max(0, min(23, (int) $segments[0]));
		$minute = count($segments) > 1 ? max(0, min(59, (int) $segments[1])) : 0;

		return $this->cron("{$minute} {$hour} * * *");
	}

	/**
	 * Run the task weekly (Sunday at midnight).
	 */
	public function weekly(): self
	{
		return $this->cron('0 0 * * 0');
	}

	/**
	 * Run the task monthly.
	 */
	public function monthly(): self
	{
		return $this->cron('0 0 1 * *');
	}

	/**
	 * Determine if the event is due to run at the given date/time.
	 */
	public function isDue(\DateTimeInterface|string $currentTime = 'now'): bool
	{
		$cron = new CronExpression($this->expression);
		return $cron->isDue($currentTime, $this->timezone);
	}
}
