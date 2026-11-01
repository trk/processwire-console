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
		$className = (string) $input->getOption('class');
		$force = (bool) $input->getOption('force');
		$asJson = (bool) $input->getOption('json');

		if (!$force) {
			if ($asJson) {
				return $this->outputJsonError($output, 'CONFIRMATION_REQUIRED', 'Use --force to run db:seed non-interactively with --json.');
			}
			if (!\Laravel\Prompts\confirm('Are you sure you want to seed the database? This might insert bulk fake data.', default: false)) {
				\Laravel\Prompts\warning('Seeding cancelled.');
				return Command::SUCCESS;
			}
		}

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
		$seeders = $discoverer->discoverClasses('seeders', '*Seeder.php', Seeder::class);

		if (empty($seeders)) {
			if ($asJson) {
				return $this->outputJsonSuccess($output, ['seeded' => [], 'message' => 'No seeders found.']);
			}
			\Laravel\Prompts\warning('No seeders found in site/seeders or modules.');
			return Command::SUCCESS;
		}

		if ($className !== 'DatabaseSeeder' && !isset($seeders[$className])) {
			$msg = "Seeder class not found: {$className}";
			if ($asJson) {
				return $this->outputJsonError($output, 'NOT_FOUND', $msg);
			}
			\Laravel\Prompts\error($msg);
			return Command::FAILURE;
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
			if ($asJson) {
				return $this->outputJsonError($output, 'SEED_ERROR', $e->getMessage());
			}
			\Laravel\Prompts\error($e->getMessage());
			return Command::FAILURE;
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
