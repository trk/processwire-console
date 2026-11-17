<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Composer\InstalledVersions;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Traits\InteractWithProcessWire;
use function Laravel\Prompts\table;

final class VersionCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('version')
			->setDescription('Show version information for PHP, ProcessWire, and ProcessWire Console.')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$asJson = (bool) $input->getOption('json');
		$rows = [];
		$items = [];

		$rows[] = ['php', PHP_VERSION];
		$items['php'] = PHP_VERSION;

		$pwVersion = 'Unknown';
		if (class_exists(\ProcessWire\ProcessWire::class)) {
			$pwVersion = \ProcessWire\ProcessWire::versionMajor . '.'
				. \ProcessWire\ProcessWire::versionMinor . '.'
				. \ProcessWire\ProcessWire::versionRevision;

			$suffix = defined(\ProcessWire\ProcessWire::class . '::versionSuffix')
				? trim((string) constant(\ProcessWire\ProcessWire::class . '::versionSuffix'))
				: '';
			if ($suffix !== '') {
				$pwVersion .= '-' . $suffix;
			}
		}
		$rows[] = ['processwire/core', $pwVersion];
		$items['processwire/core'] = $pwVersion;

		$consoleVersion = $this->getVersion('trk/processwire-console');
		$rows[] = ['trk/processwire-console', $consoleVersion];
		$items['trk/processwire-console'] = $consoleVersion;

		$boostVersion = $this->getVersion('trk/processwire-boost');
		if ($boostVersion !== 'Unknown') {
			$rows[] = ['trk/processwire-boost', $boostVersion];
			$items['trk/processwire-boost'] = $boostVersion;
		}

		if ($asJson) {
			return $this->outputJsonSuccess($output, ['versions' => $items]);
		}

		table(['Package', 'Version'], $rows);

		return Command::SUCCESS;
	}

	private function getVersion(string $package): string
	{
		try {
			if (class_exists(InstalledVersions::class) && InstalledVersions::isInstalled($package)) {
				return (string) (InstalledVersions::getPrettyVersion($package) ?? InstalledVersions::getVersion($package) ?? 'Unknown');
			}
		} catch (\Throwable) {
			// ignore
		}

		return 'Unknown';
	}
}
