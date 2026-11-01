<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Traits\InteractWithProcessWire;
use function Laravel\Prompts\info;

final class CacheClearCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('cache:clear')
			->setDescription('Clear various ProcessWire caches.')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$asJson = (bool) $input->getOption('json');
		$cleared = ['modules'];

		\ProcessWire\wire('modules')->resetCache();
		if (!$asJson) {
			info('Module cache cleared.');
		}

		$compiledPath = \ProcessWire\wire('config')->paths->cache . 'FileCompiler/';
		if (is_dir($compiledPath)) {
			$files = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($compiledPath, \RecursiveDirectoryIterator::SKIP_DOTS),
				\RecursiveIteratorIterator::CHILD_FIRST
			);
			foreach ($files as $fileinfo) {
				$realPath = $fileinfo->getRealPath();
				if ($realPath === false) {
					continue;
				}
				if ($fileinfo->isDir()) {
					@rmdir($realPath);
				} else {
					@unlink($realPath);
				}
			}
			$cleared[] = 'FileCompiler';
			if (!$asJson) {
				info('Compiled templates (FileCompiler) cache cleared.');
			}
		}

		if ($asJson) {
			return $this->outputJsonSuccess($output, ['cleared' => $cleared]);
		}

		info('ProcessWire caches cleared successfully.');

		return Command::SUCCESS;
	}
}
