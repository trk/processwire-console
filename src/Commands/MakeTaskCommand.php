<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Traits\InteractWithProcessWire;

final class MakeTaskCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('make:task')
			->setDescription('Create a new Scheduled Task class.')
			->addArgument('name', InputArgument::REQUIRED, 'The name of the Task class (e.g. CleanupLogsTask)')
			->addOption('module', 'm', InputOption::VALUE_REQUIRED, 'Module name to place the task in (e.g. MyCustomModule)')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		return $this->scaffoldFeatureClass(
			input: $input,
			output: $output,
			label: 'task',
			suffix: 'Task',
			directory: 'schedule',
			defaultNamespace: 'Site\\Schedule',
			stubFile: 'task.stub',
			successMessagePrefix: 'Created scheduled task'
		);
	}
}
