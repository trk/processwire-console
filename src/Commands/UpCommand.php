<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Traits\InteractWithProcessWire;

final class UpCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('up')
			->setDescription('Bring the application out of maintenance mode')
			->addOption('json', null, InputOption::VALUE_NONE, 'Output results as JSON');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$json = (bool) $input->getOption('json');
		$wire = \ProcessWire\wire();
		if (!($wire instanceof \ProcessWire\ProcessWire)) {
			return $this->failCommand($output, $json, 'ENV_NOT_FOUND', 'ProcessWire environment not found.');
		}

		try {
			$file = $wire->config->paths->site . 'down.json';

			if (!file_exists($file)) {
				if ($json) {
					return $this->outputJsonSuccess($output, ['message' => 'Application is already up.']);
				}
				\Laravel\Prompts\info('Application is already up.');
				return Command::SUCCESS;
			}

			if (!@unlink($file) && file_exists($file)) {
				throw new \RuntimeException("Unable to delete {$file}.");
			}

			if ($json) {
				return $this->outputJsonSuccess($output, ['message' => 'Application is now live.']);
			}
			\Laravel\Prompts\info('Application is now live.');

			return Command::SUCCESS;
		} catch (\Throwable $e) {
			return $this->failCommand($output, $json, 'MAINTENANCE_ERROR', 'Failed to bring application out of maintenance mode: ' . $e->getMessage());
		}
	}
}
