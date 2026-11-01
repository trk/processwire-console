<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Traits\InteractWithProcessWire;
use function Laravel\Prompts\error;
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
		$path = $input->getOption('path');
		$dryRun = (bool) $input->getOption('dry-run');
		$asJson = (bool) $input->getOption('json');

		if (!$id && !$path && !$asJson) {
			$id = $this->searchPage('Select a page to unpublish');
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

		$result = ['id' => $page->id, 'path' => $page->path, 'wasUnpublished' => $page->isUnpublished(), 'dryRun' => $dryRun];

		if ($dryRun) {
			if ($asJson) {
				return $this->outputJsonSuccess($output, $result);
			}
			note("Dry-run: would unpublish page #{$page->id} ({$page->path}).");
			return Command::SUCCESS;
		}

		$page->addStatus(\ProcessWire\Page::statusUnpublished);
		$page->save();

		if ($asJson) {
			return $this->outputJsonSuccess($output, $result + ['unpublished' => true]);
		}

		info("Unpublished page #{$page->id} ({$page->path}).");
		return Command::SUCCESS;
	}
}
