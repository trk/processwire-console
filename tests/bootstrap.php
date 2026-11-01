<?php

declare(strict_types=1);

$autoloadCandidates = [
	dirname(__DIR__) . '/vendor/autoload.php',
	dirname(__DIR__, 3) . '/vendor/autoload.php',
];

foreach ($autoloadCandidates as $candidate) {
	if (file_exists($candidate)) {
		require_once $candidate;
		break;
	}
}

spl_autoload_register(static function (string $class): void {
	$prefixes = [
		'Totoglu\\Console\\Tests\\' => dirname(__DIR__) . '/tests/',
		'Totoglu\\Console\\' => dirname(__DIR__) . '/src/',
	];

	foreach ($prefixes as $prefix => $baseDir) {
		if (str_starts_with($class, $prefix)) {
			$relativeClass = substr($class, strlen($prefix));
			$file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
			if (is_file($file)) {
				require_once $file;
				return;
			}
		}
	}
}, true, true);
