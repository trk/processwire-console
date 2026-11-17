<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Database\Seeder;
use Totoglu\Console\Support\FeatureDiscoverer;
use Totoglu\Console\Traits\InteractWithProcessWire;

final class DbSeedCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('db:seed')
			->setDescription('Seed the database with records.')
			->addOption('class', null, InputOption::VALUE_REQUIRED, 'The class name of the root seeder', 'DatabaseSeeder')
			->addOption('force', 'f', InputOption::VALUE_NONE, 'Force the operation to run')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$className = trim((string) $input->getOption('class'));
		$force = (bool) $input->getOption('force');
		$asJson = (bool) $input->getOption('json');

		if (!$this->isValidIdentifier($className)) {
			return $this->failCommand($output, $asJson, 'INVALID_CLASS', "Invalid seeder class name '{$className}'.");
		}

		if (!$force) {
			if ($asJson || !$input->isInteractive()) {
				return $this->failCommand($output, $asJson, 'CONFIRMATION_REQUIRED', 'Use --force to run db:seed non-interactively.');
			}
			if (!\Laravel\Prompts\confirm('Are you sure you want to seed the database? This might insert bulk fake data.', default: false)) {
				\Laravel\Prompts\warning('Seeding cancelled.');
				return Command::SUCCESS;
			}
		}

		$wire = \ProcessWire\wire();
		if (!($wire instanceof \ProcessWire\ProcessWire)) {
			return $this->failCommand($output, $asJson, 'ENV_NOT_FOUND', 'ProcessWire environment not found.');
		}

		$discoverer = new FeatureDiscoverer($wire);
		$seeders = $discoverer->discoverClasses('seeders', '*Seeder.php', Seeder::class);

		if ($seeders === []) {
			if ($asJson) {
				return $this->outputJsonSuccess($output, ['seeded' => [], 'message' => 'No seeders found.']);
			}
			\Laravel\Prompts\warning('No seeders found in site/seeders or modules.');
			return Command::SUCCESS;
		}

		if ($className !== 'DatabaseSeeder' && !isset($seeders[$className])) {
			return $this->failCommand($output, $asJson, 'NOT_FOUND', "Seeder class not found: {$className}");
		}

		$seeded = [];

		try {
			if (isset($seeders[$className])) {
				$this->runSeeder($wire, $className, $seeders[$className], $asJson);
				$seeded[] = $className;
			} else {
				if (!$asJson) {
					\Laravel\Prompts\info('Running all discovered seeders...');
				}
				foreach ($seeders as $name => $fqcn) {
					$this->runSeeder($wire, $name, $fqcn, $asJson);
					$seeded[] = $name;
				}
			}
		} catch (\Throwable $e) {
			return $this->failCommand($output, $asJson, 'SEED_ERROR', $e->getMessage());
		}

		if ($asJson) {
			return $this->outputJsonSuccess($output, ['seeded' => $seeded]);
		}

		\Laravel\Prompts\info('Database seeding completed successfully.');

		return Command::SUCCESS;
	}

	/**
	 * @param class-string<Seeder> $fqcn
	 */
	private function runSeeder(\ProcessWire\ProcessWire $wire, string $name, string $fqcn, bool $asJson): void
	{
		if (!$asJson) {
			\Laravel\Prompts\info("Seeding: {$name}...");
		}
		$instance = new $fqcn($wire);
		$instance->run();

		if (!$asJson) {
			\Laravel\Prompts\note("Seeded: {$name}");
		}
	}
}
