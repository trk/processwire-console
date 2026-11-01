<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Scheduling\Event;
use Totoglu\Console\Scheduling\Task;
use Totoglu\Console\Support\FeatureDiscoverer;
use Totoglu\Console\Traits\InteractWithProcessWire;

final class ScheduleRunCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('schedule:run')
			->setDescription('Run the scheduled commands')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$asJson = (bool) $input->getOption('json');
		$wire = \ProcessWire\wire();
		if (!$wire) {
			$msg = 'ProcessWire environment not found.';
			if ($asJson) {
				return $this->outputJsonError($output, 'ENV_NOT_FOUND', $msg);
			}
			\Laravel\Prompts\error($msg);
			return Command::FAILURE;
		}

		$discoverer = new FeatureDiscoverer($wire);
		$tasks = $discoverer->discoverClasses('schedule', '*Task.php', Task::class);

		if (empty($tasks)) {
			if ($asJson) {
				return $this->outputJsonSuccess($output, ['executed' => [], 'errors' => []]);
			}
			\Laravel\Prompts\info('No scheduled tasks found.');
			return Command::SUCCESS;
		}

		$executed = [];
		$errors = [];

		foreach ($tasks as $name => $fqcn) {
			/** @var Task $instance */
			$instance = new $fqcn($wire);
			$event = new Event();
			$instance->schedule($event);

			if ($event->isDue()) {
				if (!$asJson) {
					\Laravel\Prompts\info("Running scheduled task: {$name}");
				}
				try {
					$instance->handle();
					$executed[] = $name;
				} catch (\Throwable $e) {
					$errors[$name] = $e->getMessage();
					if (!$asJson) {
						\Laravel\Prompts\error("Task {$name} failed: " . $e->getMessage());
					}
				}
			}
		}

		if ($asJson) {
			return $this->outputJsonSuccess($output, [
				'executed' => $executed,
				'errors' => $errors,
			]);
		}

		if (empty($executed) && empty($errors)) {
			\Laravel\Prompts\info('No scheduled commands are ready to run.');
		}

		return Command::SUCCESS;
	}
}
