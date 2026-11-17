<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Traits\InteractWithProcessWire;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;

final class PageRestoreCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('page:restore')
			->setDescription('Restore a trashed page to its original location.')
			->addOption('id', null, InputOption::VALUE_OPTIONAL, 'Page ID')
			->addOption('path', null, InputOption::VALUE_OPTIONAL, 'Page path')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write changes')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$id = $input->getOption('id');
		$path = $input->getOption('path') !== null ? (string) $input->getOption('path') : null;
		$dryRun = (bool) $input->getOption('dry-run');
		$asJson = (bool) $input->getOption('json');

		if (!$id && !$path && $this->canPrompt($input, $asJson)) {
			$id = $this->searchPage('Select a page to restore from Trash', true);
			if ($id === '') {
				return Command::SUCCESS;
			}
		}

		if (!$id && !$path) {
			return $this->failCommand($output, $asJson, 'MISSING_IDENTIFIER', 'Provide --id or --path.');
		}

		$page = $this->resolvePage($id, $path);
		if ($page === null) {
			return $this->failCommand($output, $asJson, 'NOT_FOUND', 'Page not found.');
		}

		if (!$page->isTrash()) {
			return $this->failCommand($output, $asJson, 'NOT_IN_TRASH', "Page #{$page->id} ({$page->path}) is not in Trash.");
		}

		$result = ['id' => (int) $page->id, 'path' => (string) $page->path, 'dryRun' => $dryRun];
		if ($dryRun) {
			if ($asJson) {
				return $this->outputJsonSuccess($output, $result);
			}
			note("Dry-run: would restore page #{$page->id}.");
			return Command::SUCCESS;
		}

		try {
			$ok = \ProcessWire\wire('pages')->restore($page);
		} catch (\Throwable $e) {
			return $this->failCommand($output, $asJson, 'RESTORE_FAILED', $e->getMessage());
		}
		if (!$ok) {
			return $this->failCommand($output, $asJson, 'RESTORE_FAILED', "Restore failed for page #{$page->id}.");
		}

		if ($asJson) {
			return $this->outputJsonSuccess($output, $result + ['restored' => true, 'path' => $page->path]);
		}

		info("Restored page #{$page->id} to {$page->path}.");
		return Command::SUCCESS;
	}
}
