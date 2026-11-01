<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Queue\QueueManager;
use Totoglu\Console\Traits\InteractWithProcessWire;

final class QueueFailedCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('queue:failed')
			->setDescription('List all failed queue jobs.')
			->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Maximum number of failed jobs to list', '50')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$asJson = (bool) $input->getOption('json');
		$limit = max(1, (int) $input->getOption('limit'));

		try {
			$manager = new QueueManager();
			$failedJobs = $manager->getFailedJobs($limit);

			if (empty($failedJobs)) {
				if ($asJson) {
					return $this->outputJsonSuccess($output, ['items' => [], 'total' => 0]);
				}
				\Laravel\Prompts\info('No failed jobs found.');
				return Command::SUCCESS;
			}

			$items = [];
			$rows = [];
			foreach ($failedJobs as $job) {
				$payload = json_decode((string) ($job['payload'] ?? ''), true);
				$class = is_array($payload) ? (string) ($payload['class'] ?? 'UnknownClass') : 'UnknownClass';

				$items[] = [
					'id' => (int) $job['id'],
					'queue' => (string) $job['queue'],
					'class' => $class,
					'failed_at' => (string) $job['failed_at'],
				];

				$rows[] = [
					(string) $job['id'],
					(string) $job['queue'],
					$class,
					(string) $job['failed_at'],
				];
			}

			if ($asJson) {
				return $this->outputJsonSuccess($output, ['items' => $items, 'total' => count($items)]);
			}

			\Laravel\Prompts\table(
				['ID', 'Queue', 'Class', 'Failed At'],
				$rows
			);

			return Command::SUCCESS;
		} catch (\PDOException $e) {
			if (str_contains($e->getMessage(), 'Table') && str_contains($e->getMessage(), "doesn't exist")) {
				if ($asJson) {
					return $this->outputJsonSuccess($output, ['items' => [], 'total' => 0]);
				}
				\Laravel\Prompts\info('The failed_jobs table does not exist. Run `wire queue:table` to create it.');
				return Command::SUCCESS;
			}
			if ($asJson) {
				return $this->outputJsonError($output, 'QUEUE_ERROR', $e->getMessage());
			}
			throw $e;
		}
	}
}
