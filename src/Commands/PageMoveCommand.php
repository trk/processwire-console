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

final class PageMoveCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('page:move')
			->setDescription('Move a page to a new parent.')
			->addOption('id', null, InputOption::VALUE_OPTIONAL, 'Page ID')
			->addOption('path', null, InputOption::VALUE_OPTIONAL, 'Page path')
			->addOption('parent', 'p', InputOption::VALUE_OPTIONAL, 'New parent path or ID')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write changes')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$id = $input->getOption('id');
		$path = $input->getOption('path') !== null ? (string) $input->getOption('path') : null;
		$parentArg = trim((string) $input->getOption('parent'));
		$dryRun = (bool) $input->getOption('dry-run');
		$asJson = (bool) $input->getOption('json');

		if (!$id && !$path && $this->canPrompt($input, $asJson)) {
			$id = $this->searchPage('Select a page to move');
			if ($id === '') {
				return Command::SUCCESS;
			}
		}

		if ($parentArg === '' && $this->canPrompt($input, $asJson)) {
			$parentArg = (string) $this->searchPage('Select the new parent page');
		}

		if ((!$id && !$path) || $parentArg === '') {
			return $this->failCommand($output, $asJson, 'MISSING_OPTIONS', 'Provide --id or --path and --parent.');
		}

		$page = $this->resolvePage($id, $path);
		if ($page === null) {
			return $this->failCommand($output, $asJson, 'NOT_FOUND', 'Page not found.');
		}

		if ($this->isProtectedSystemPage($page)) {
			return $this->failCommand($output, $asJson, 'SYSTEM_PAGE_PROTECTED', "Page #{$page->id} ({$page->path}) is a system page and cannot be moved.");
		}

		$newParent = ctype_digit($parentArg)
			? $this->resolvePage($parentArg, null)
			: $this->resolvePage(null, $parentArg);
		if ($newParent === null) {
			return $this->failCommand($output, $asJson, 'NOT_FOUND', "New parent not found: {$parentArg}");
		}

		if ((int) $newParent->id === (int) $page->id || $newParent->parents->has($page)) {
			return $this->failCommand($output, $asJson, 'INVALID_PARENT', 'Cannot move a page into itself or one of its descendants.');
		}

		$fromPath = $page->parent instanceof \ProcessWire\Page ? (string) $page->parent->path : '/';
		$toPath = (string) $newParent->path;

		$result = ['id' => (int) $page->id, 'from' => $fromPath, 'to' => $toPath, 'dryRun' => $dryRun];
		if ($dryRun) {
			if ($asJson) {
				return $this->outputJsonSuccess($output, $result);
			}
			note("Dry-run: would move page #{$page->id} from {$fromPath} to {$toPath}.");
			return Command::SUCCESS;
		}

		$page->of(false);
		$page->parent = $newParent;
		try {
			$page->save();
		} catch (\Throwable $e) {
			return $this->failCommand($output, $asJson, 'SAVE_FAILED', $e->getMessage());
		}

		if ($asJson) {
			return $this->outputJsonSuccess($output, $result + ['moved' => true]);
		}

		info("Moved page #{$page->id} to {$newParent->path}.");
		return Command::SUCCESS;
	}
}
