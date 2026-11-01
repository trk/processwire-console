<?php

declare(strict_types=1);

namespace Totoglu\Console\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Totoglu\Console\Scheduling\Event;

final class EventScheduleTest extends TestCase
{
	public function testEventCronExpressionsAndFluentFrequencies(): void
	{
		$event = new Event('UTC');

		// Default is '* * * * *', which is always due
		$this->assertSame('* * * * *', $event->getExpression());
		$this->assertTrue($event->isDue());

		$event->everyFiveMinutes();
		$this->assertSame('*/5 * * * *', $event->getExpression());

		$event->hourly();
		$this->assertSame('0 * * * *', $event->getExpression());

		$event->daily();
		$this->assertSame('0 0 * * *', $event->getExpression());

		$event->dailyAt('14:30');
		$this->assertSame('30 14 * * *', $event->getExpression());

		$event->weekly();
		$this->assertSame('0 0 * * 0', $event->getExpression());

		$event->monthly();
		$this->assertSame('0 0 1 * *', $event->getExpression());

		$event->everyMinute();
		$this->assertSame('* * * * *', $event->getExpression());
		$this->assertTrue($event->isDue());

		$event->cron('30 14 * * *');
		$this->assertTrue($event->isDue(new \DateTimeImmutable('2026-11-01 14:30:00', new \DateTimeZone('UTC'))));
		$this->assertFalse($event->isDue(new \DateTimeImmutable('2026-11-01 14:31:00', new \DateTimeZone('UTC'))));
	}
}
