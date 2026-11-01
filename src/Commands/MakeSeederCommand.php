<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Traits\InteractWithProcessWire;

final class MakeSeederCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('make:seeder')
			->setDescription('Create a new Database Seeder class.')
			->addArgument('name', InputArgument::REQUIRED, 'The name of the Seeder class (e.g. UsersSeeder)')
			->addOption('module', 'm', InputOption::VALUE_REQUIRED, 'Module name to place the seeder in (e.g. MyCustomModule)')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		return $this->scaffoldFeatureClass(
			input: $input,
			output: $output,
			label: 'seeder',
			suffix: 'Seeder',
			directory: 'seeders',
			defaultNamespace: 'Site\\Seeders',
			stubFile: 'seeder.stub',
			successMessagePrefix: 'Created seeder class'
		);
	}
}
