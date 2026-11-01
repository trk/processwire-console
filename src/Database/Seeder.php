<?php

declare(strict_types=1);

namespace Totoglu\Console\Database;

use ProcessWire\ProcessWire;
use Totoglu\Console\Support\FeatureDiscoverer;

abstract class Seeder
{
	/**
	 * Optional Faker instance if fakerphp/faker is installed.
	 */
	protected mixed $faker = null;

	public function __construct(protected ProcessWire $wire)
	{
		if (class_exists('\\Faker\\Factory')) {
			$this->faker = \Faker\Factory::create();
		}
	}

	/**
	 * Run the database seeders.
	 */
	abstract public function run(): void;

	/**
	 * Helper to safely call another Seeder class.
	 */
	protected function call(string $class): void
	{
		$resolved = $this->resolveSeederClass($class);
		if ($resolved === null) {
			throw new \RuntimeException("Seeder class not found: {$class}");
		}

		/** @var Seeder $seeder */
		$seeder = new $resolved($this->wire);
		$seeder->run();
	}

	/**
	 * @return class-string<Seeder>|null
	 */
	private function resolveSeederClass(string $class): ?string
	{
		foreach ([$class, "Site\\Seeders\\{$class}", "ProcessWire\\{$class}"] as $candidate) {
			if (class_exists($candidate) && is_subclass_of($candidate, self::class)) {
				return $candidate;
			}
		}

		$discoverer = new FeatureDiscoverer($this->wire);
		$seeders = $discoverer->discoverClasses('seeders', '*.php', self::class);
		if (isset($seeders[$class])) {
			return $seeders[$class];
		}

		$shortName = basename(str_replace('\\', '/', $class));
		return $seeders[$shortName] ?? null;
	}
}
