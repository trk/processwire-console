<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Migration\Migrator;
use Totoglu\Console\Traits\InteractWithProcessWire;

final class MigrateStatusCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('migrate:status')
			->setDescription('Show the status of each migration.')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$asJson = (bool) $input->getOption('json');

		$migrator = new Migrator();
		$status = $migrator->getStatus();

		if (empty($status)) {
			if ($asJson) {
				return $this->outputJsonSuccess($output, ['items' => [], 'total' => 0]);
			}
			\Laravel\Prompts\note('No migration files found in ' . $migrator->getMigrationsPath());
			return Command::SUCCESS;
		}

		if ($asJson) {
			return $this->outputJsonSuccess($output, ['items' => $status, 'total' => count($status)]);
		}

		$appliedCount = 0;
		$pendingCount = 0;
		$rows = [];

		foreach ($status as $row) {
			$statusLabel = $row['status'] === 'applied' ? 'Applied' : 'Pending';
			$batchLabel = $row['batch'] !== null ? (string) $row['batch'] : '-';

			$rows[] = [$row['name'], $statusLabel, $batchLabel];

			if ($row['status'] === 'applied') {
				$appliedCount++;
			} else {
				$pendingCount++;
			}
		}

		\Laravel\Prompts\table(
			headers: ['Migration', 'Status', 'Batch'],
			rows: $rows
		);

		\Laravel\Prompts\note("{$appliedCount} applied, {$pendingCount} pending.");

		return Command::SUCCESS;
	}
}
