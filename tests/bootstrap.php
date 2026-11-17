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
			if (preg_match('/^[A-Za-z_][A-Za-z0-9_\\\\]*$/', $relativeClass) !== 1) {
				return;
			}
			$resolvedBase = realpath($baseDir);
			if ($resolvedBase === false) {
				continue;
			}
			$file = $resolvedBase . '/' . str_replace('\\', '/', $relativeClass) . '.php';
			$realFile = realpath($file);
			if ($realFile !== false && str_starts_with($realFile, $resolvedBase . DIRECTORY_SEPARATOR) && is_file($realFile)) {
				require_once $realFile;
				return;
			}
		}
	}
}, true, true);
