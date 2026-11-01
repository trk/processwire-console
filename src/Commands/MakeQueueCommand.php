<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Traits\InteractWithProcessWire;

final class MakeQueueCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('make:queue')
			->setDescription('Create a new Queue class.')
			->addArgument('name', InputArgument::REQUIRED, 'The name of the Queue class (e.g. SendEmailQueue)')
			->addOption('module', 'm', InputOption::VALUE_REQUIRED, 'Module name to place the queue in (e.g. MyCustomModule)')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		return $this->scaffoldFeatureClass(
			input: $input,
			output: $output,
			label: 'queue',
			suffix: 'Queue',
			directory: 'queue',
			defaultNamespace: 'Site\\Queue',
			stubFile: 'queue.stub',
			successMessagePrefix: 'Created queue class'
		);
	}
}
