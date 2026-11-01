<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Queue\QueueManager;
use Totoglu\Console\Traits\InteractWithProcessWire;

final class QueueTableCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('queue:table')
			->setDescription('Create the queue_jobs and failed_jobs tables if they do not exist.')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$asJson = (bool) $input->getOption('json');

		try {
			$manager = new QueueManager();
			$manager->ensureTables();

			if ($asJson) {
				return $this->outputJsonSuccess($output, [
					'tables' => ['queue_jobs', 'failed_jobs'],
					'created' => true,
				]);
			}

			\Laravel\Prompts\info('Queue tables [queue_jobs, failed_jobs] have been created or verified.');
			return Command::SUCCESS;
		} catch (\Throwable $e) {
			$msg = 'Failed to create queue tables: ' . $e->getMessage();
			if ($asJson) {
				return $this->outputJsonError($output, 'TABLE_ERROR', $msg);
			}
			\Laravel\Prompts\error($msg);
			return Command::FAILURE;
		}
	}
}
