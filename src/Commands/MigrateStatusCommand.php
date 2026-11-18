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

		try {
			$migrator = new Migrator();
			$status = $migrator->getStatus();
		} catch (\Throwable $e) {
			return $this->failCommand($output, $asJson, 'MIGRATION_FAILED', $e->getMessage());
		}

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
		$missingCount = 0;
		$rows = [];

		foreach ($status as $row) {
			$statusLabel = match ($row['status']) {
				'applied' => 'Applied',
				'missing' => 'Missing',
				default => 'Pending',
			};
			$batchLabel = $row['batch'] !== null ? (string) $row['batch'] : '-';

			$rows[] = [$row['name'], $statusLabel, $batchLabel];

			if ($row['status'] === 'applied') {
				$appliedCount++;
			} elseif ($row['status'] === 'missing') {
				$missingCount++;
			} else {
				$pendingCount++;
			}
		}

		\Laravel\Prompts\table(
			headers: ['Migration', 'Status', 'Batch'],
			rows: $rows
		);

		$summary = "{$appliedCount} applied, {$pendingCount} pending";
		if ($missingCount > 0) {
			$summary .= ", {$missingCount} missing";
		}
		\Laravel\Prompts\note($summary . '.');

		return Command::SUCCESS;
	}
}
