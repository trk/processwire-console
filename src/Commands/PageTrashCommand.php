<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Traits\InteractWithProcessWire;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;

final class PageTrashCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('page:trash')
			->setDescription('Move a page to Trash.')
			->addOption('id', null, InputOption::VALUE_OPTIONAL, 'Page ID')
			->addOption('path', null, InputOption::VALUE_OPTIONAL, 'Page path')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write changes')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output')
			->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip interactive confirmations');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$id = $input->getOption('id');
		$path = $input->getOption('path') !== null ? (string) $input->getOption('path') : null;
		$dryRun = (bool) $input->getOption('dry-run');
		$asJson = (bool) $input->getOption('json');
		$force = (bool) $input->getOption('force');

		if (!$id && !$path && $this->canPrompt($input, $asJson, $force)) {
			$id = $this->searchPage('Select a page to move to Trash');
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

		if ($this->isProtectedSystemPage($page)) {
			return $this->failCommand($output, $asJson, 'SYSTEM_PAGE_PROTECTED', "Page #{$page->id} ({$page->path}) is a system page and cannot be trashed.");
		}

		if (!$force && !$dryRun) {
			if ($asJson || !$input->isInteractive()) {
				return $this->failCommand($output, $asJson, 'CONFIRMATION_REQUIRED', 'Use --force to run page:trash non-interactively.');
			}
			if (!confirm("Move page #{$page->id} ({$page->path}) to Trash?", default: false)) {
				note('Aborted.');
				return Command::SUCCESS;
			}
		}

		if ($dryRun) {
			if ($asJson) {
				return $this->outputJsonSuccess($output, ['id' => (int) $page->id, 'path' => (string) $page->path, 'dryRun' => true]);
			}
			note("Dry-run: would trash page #{$page->id} ({$page->path}).");
			return Command::SUCCESS;
		}

		try {
			$trashed = \ProcessWire\wire('pages')->trash($page, true);
			if (!$trashed) {
				return $this->failCommand($output, $asJson, 'TRASH_FAILED', "Trash failed for page #{$page->id}.");
			}
		} catch (\Throwable $e) {
			return $this->failCommand($output, $asJson, 'TRASH_FAILED', $e->getMessage());
		}

		if ($asJson) {
			return $this->outputJsonSuccess($output, ['id' => (int) $page->id, 'trashed' => true]);
		}

		info("Trashed page #{$page->id} ({$page->path}).");
		return Command::SUCCESS;
	}
}
