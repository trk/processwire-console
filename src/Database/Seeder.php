<?php

declare(strict_types=1);

namespace Totoglu\Console\Database;

use ProcessWire\ProcessWire;

abstract class Seeder
{
    /**
     * @var \ProcessWire\ProcessWire
     */
    protected ProcessWire $wire;

    /**
     * Optional Faker instance if fakerphp/faker is installed.
     * @var mixed
     */
    protected $faker;

    public function __construct(ProcessWire $wire)
    {
        $this->wire = $wire;

        // Provide a default Faker instance.
        // If the developer wants a specific locale, they can override this in run().
        if (class_exists('\\Faker\\Factory')) {
            $this->faker = \Faker\Factory::create();
        }
    }

    /**
     * Run the database seeders.
     */
    abstract public function run(): void;

    /**
     * Helper to safely call another Seeder class
     *
     * @param string $class
     * @return void
     */
    protected function call(string $class): void
    {
        if (class_exists($class)) {
            /** @var Seeder $seeder */
            $seeder = new $class($this->wire);
            $seeder->run();
        } else {
            \Laravel\Prompts\error("Seeder class not found: {$class}");
        }
    }
}
