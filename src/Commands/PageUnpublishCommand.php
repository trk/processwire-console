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

final class PageUnpublishCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('page:unpublish')
			->setDescription('Unpublish a page (add unpublished status).')
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
			$id = $this->searchPage('Select a page to unpublish');
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

		if ($this->isRbacPage($page)) {
			return $this->failCommand($output, $asJson, 'RBAC_PAGE_PROTECTED', 'Users, roles, and permissions cannot be unpublished via page:unpublish.');
		}

		if ($this->isProtectedSystemPage($page)) {
			return $this->failCommand($output, $asJson, 'SYSTEM_PAGE_PROTECTED', "Page #{$page->id} ({$page->path}) is a system page and cannot be unpublished.");
		}

		if ($page->template instanceof \ProcessWire\Template && !empty($page->template->noUnpublish)) {
			return $this->failCommand($output, $asJson, 'UNPUBLISH_FORBIDDEN', "Template '{$page->template->name}' forbids unpublishing pages (noUnpublish=1).");
		}

		$result = ['id' => $page->id, 'path' => $page->path, 'wasUnpublished' => $page->isUnpublished(), 'dryRun' => $dryRun];

		if ($dryRun) {
			if ($asJson) {
				return $this->outputJsonSuccess($output, $result);
			}
			note("Dry-run: would unpublish page #{$page->id} ({$page->path}).");
			return Command::SUCCESS;
		}

		$page->of(false);
		$page->addStatus(\ProcessWire\Page::statusUnpublished);
		try {
			$page->save();
		} catch (\Throwable $e) {
			return $this->failCommand($output, $asJson, 'SAVE_FAILED', $e->getMessage());
		}

		if ($asJson) {
			return $this->outputJsonSuccess($output, $result + ['unpublished' => true]);
		}

		info("Unpublished page #{$page->id} ({$page->path}).");
		return Command::SUCCESS;
	}
}
