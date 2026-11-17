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
		if (!($wire instanceof \ProcessWire\ProcessWire)) {
			return $this->failCommand($output, $asJson, 'ENV_NOT_FOUND', 'ProcessWire environment not found.');
		}

		$discoverer = new FeatureDiscoverer($wire);
		$tasks = $discoverer->discoverClasses('schedule', '*Task.php', Task::class);

		if ($tasks === []) {
			if ($asJson) {
				return $this->outputJsonSuccess($output, ['executed' => [], 'errors' => []]);
			}
			\Laravel\Prompts\info('No scheduled tasks found.');
			return Command::SUCCESS;
		}

		$executed = [];
		$errors = [];

		foreach ($tasks as $name => $fqcn) {
			try {
				/** @var Task $instance */
				$instance = new $fqcn($wire);
				$event = new Event();
				$instance->schedule($event);

				if ($event->isDue()) {
					if (!$asJson) {
						\Laravel\Prompts\info("Running scheduled task: {$name}");
					}
					$instance->handle();
					$executed[] = $name;
				}
			} catch (\Throwable $e) {
				$errors[$name] = $e->getMessage();
				if (!$asJson) {
					\Laravel\Prompts\error("Task {$name} failed: " . $e->getMessage());
				}
			}
		}

		if ($asJson) {
			if ($errors !== []) {
				$output->writeln((string) json_encode([
					'ok' => false,
					'data' => [
						'executed' => $executed,
						'errors' => $errors,
					],
					'error' => [
						'code' => 'SCHEDULE_FAILED',
						'message' => count($errors) . ' scheduled task(s) failed.',
					],
				], JSON_UNESCAPED_SLASHES));
				return Command::FAILURE;
			}

			return $this->outputJsonSuccess($output, [
				'executed' => $executed,
				'errors' => $errors,
			]);
		}

		if ($executed === [] && $errors === []) {
			\Laravel\Prompts\info('No scheduled commands are ready to run.');
		}

		return $errors === [] ? Command::SUCCESS : Command::FAILURE;
	}
}
