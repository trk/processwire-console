<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Traits\InteractWithProcessWire;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
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
		$path = $input->getOption('path');
		$dryRun = (bool) $input->getOption('dry-run');
		$asJson = (bool) $input->getOption('json');
		$force = (bool) $input->getOption('force');

		if (!$id && !$path && !$asJson) {
			$id = $this->searchPage('Select a page to move to Trash');
			if ($id === '') {
				return Command::SUCCESS;
			}
		}

		if (!$id && !$path) {
			$msg = 'Provide --id or --path.';
			return $asJson ? $this->outputJsonError($output, 'MISSING_IDENTIFIER', $msg) : (error($msg) ?? Command::FAILURE);
		}

		$pages = \ProcessWire\wire('pages');
		$page = $id ? $pages->get((int) $id) : $pages->get((string) $path);
		if (!$page || !$page->id) {
			$msg = 'Page not found.';
			return $asJson ? $this->outputJsonError($output, 'NOT_FOUND', $msg) : (error($msg) ?? Command::FAILURE);
		}

		if (!$force && !$dryRun) {
			if ($asJson) {
				return $this->outputJsonError($output, 'CONFIRMATION_REQUIRED', 'Use --force to run page:trash non-interactively with --json.');
			}
			if (!confirm("Move page #{$page->id} ({$page->path}) to Trash?", false)) {
				note('Aborted.');
				return Command::SUCCESS;
			}
		}

		if ($dryRun) {
			if ($asJson) {
				return $this->outputJsonSuccess($output, ['id' => $page->id, 'path' => $page->path, 'dryRun' => true]);
			}
			note("Dry-run: would trash page #{$page->id} ({$page->path}).");
			return Command::SUCCESS;
		}

		$pages->trash($page, true);

		if ($asJson) {
			return $this->outputJsonSuccess($output, ['id' => $page->id, 'trashed' => true]);
		}

		info("Trashed page #{$page->id} ({$page->path}).");
		return Command::SUCCESS;
	}
}
