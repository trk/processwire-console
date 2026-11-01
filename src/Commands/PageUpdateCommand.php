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
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

final class PageUpdateCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('page:update')
			->setDescription('Update fields and/or status on a page.')
			->addOption('id', null, InputOption::VALUE_OPTIONAL, 'Page ID')
			->addOption('path', null, InputOption::VALUE_OPTIONAL, 'Page path')
			->addOption('title', null, InputOption::VALUE_OPTIONAL, 'New page title')
			->addOption('name', null, InputOption::VALUE_OPTIONAL, 'New URL-safe page name')
			->addOption('parent', null, InputOption::VALUE_OPTIONAL, 'New parent path or ID')
			->addOption('template', null, InputOption::VALUE_OPTIONAL, 'New template name')
			->addOption('set', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Set field value as key=value (repeatable or comma-separated)')
			->addOption('status', null, InputOption::VALUE_OPTIONAL, 'Optional status: published|unpublished|hidden|locked|unlocked|publish|unpublish|hide|show|lock|unlock')
			->addOption('interactive', 'i', InputOption::VALUE_NONE, 'Prompt for page and field values')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write changes')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$id = $input->getOption('id');
		$path = $input->getOption('path');
		$titleOpt = $input->getOption('title') !== null ? (string) $input->getOption('title') : null;
		$nameOpt = $input->getOption('name') !== null ? (string) $input->getOption('name') : null;
		$parentOpt = $input->getOption('parent') !== null ? (string) $input->getOption('parent') : null;
		$templateOpt = $input->getOption('template') !== null ? (string) $input->getOption('template') : null;
		$setRaw = $input->getOption('set');
		$statusOp = $input->getOption('status') ? strtolower((string) $input->getOption('status')) : '';
		$wantInteractive = (bool) $input->getOption('interactive');
		$dryRun = (bool) $input->getOption('dry-run');
		$asJson = (bool) $input->getOption('json');

		if (($wantInteractive && $input->isInteractive() && !$asJson) && (!$id && !$path)) {
			$path = (string) $this->searchPage('Select page to update');
			if ($path === '') {
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

		$changes = $this->parseKeyValueSets($setRaw);

		if ($titleOpt !== null) {
			$changes['title'] = $titleOpt;
		}
		if ($nameOpt !== null) {
			$changes['name'] = $nameOpt;
		}

		$newParent = null;
		if ($parentOpt !== null && $parentOpt !== '') {
			$newParent = is_numeric($parentOpt) ? $pages->get((int) $parentOpt) : $pages->get($parentOpt);
			if (!$newParent || !$newParent->id) {
				$msg = "Parent not found: {$parentOpt}";
				return $asJson ? $this->outputJsonError($output, 'NOT_FOUND', $msg) : (error($msg) ?? Command::FAILURE);
			}
		}

		$newTemplate = null;
		if ($templateOpt !== null && $templateOpt !== '') {
			$newTemplate = \ProcessWire\wire('templates')->get($templateOpt);
			if (!$newTemplate || !$newTemplate->id) {
				$msg = "Template not found: {$templateOpt}";
				return $asJson ? $this->outputJsonError($output, 'NOT_FOUND', $msg) : (error($msg) ?? Command::FAILURE);
			}
		}

		if ($wantInteractive && $input->isInteractive() && !$asJson) {
			$statusChoices = ['none', 'publish', 'unpublish', 'hide', 'show', 'lock', 'unlock'];
			if (!$statusOp) {
				$sel = select('Status change', $statusChoices, default: 'none');
				$statusOp = $sel !== 'none' ? $sel : '';
			}
			$fill = confirm('Edit template fields now?', default: false);
			if ($fill) {
				$sanitizer = \ProcessWire\wire('sanitizer');
				foreach ($page->template->fieldgroup as $f) {
					$fname = $f->name;
					$ftype = $f->type;
					$label = $f->label ?: $fname;
					$desc = $f->description ?: '';
					$prompt = trim($label . ($desc ? " — {$desc}" : ''));
					$current = (string) $page->get($fname);
					$val = null;
					if ($ftype instanceof \ProcessWire\FieldtypeText) {
						$val = text($prompt, default: $current, required: false);
						$val = $sanitizer->text($val);
					} elseif ($ftype instanceof \ProcessWire\FieldtypeTextarea) {
						$val = text($prompt, default: $current, required: false);
						$val = $sanitizer->textarea($val);
					}
					if ($val !== null && $val !== $current) {
						$changes[$fname] = $val;
					}
				}
			}
		}

		$result = [
			'id' => $page->id,
			'path' => $page->path,
			'changes' => $changes,
			'parent' => $newParent ? $newParent->path : null,
			'template' => $newTemplate ? $newTemplate->name : null,
			'status' => $statusOp,
			'dryRun' => $dryRun,
		];

		if ($dryRun) {
			if ($asJson) {
				return $this->outputJsonSuccess($output, $result);
			}
			note("Dry-run: would update page #{$page->id} with: " . json_encode($changes) . " and status '{$statusOp}'.");
			return Command::SUCCESS;
		}

		if ($newParent !== null) {
			$page->parent = $newParent;
		}
		if ($newTemplate !== null) {
			$page->template = $newTemplate;
		}

		foreach ($changes as $k => $v) {
			try {
				$page->set($k, $v);
			} catch (\Throwable $e) {
				if (!$asJson) {
					warning("Field '{$k}': " . $e->getMessage());
				}
			}
		}

		if ($statusOp !== '') {
			switch ($statusOp) {
				case 'publish':
				case 'published':
					$page->removeStatus(\ProcessWire\Page::statusUnpublished);
					break;
				case 'unpublish':
				case 'unpublished':
					$page->addStatus(\ProcessWire\Page::statusUnpublished);
					break;
				case 'hide':
				case 'hidden':
					$page->addStatus(\ProcessWire\Page::statusHidden);
					break;
				case 'show':
					$page->removeStatus(\ProcessWire\Page::statusHidden);
					break;
				case 'lock':
				case 'locked':
					$page->addStatus(\ProcessWire\Page::statusLocked);
					break;
				case 'unlock':
				case 'unlocked':
					$page->removeStatus(\ProcessWire\Page::statusLocked);
					break;
			}
		}

		$page->save();

		if ($asJson) {
			return $this->outputJsonSuccess($output, $result + ['saved' => true]);
		}

		info("Updated page #{$page->id}.");
		return Command::SUCCESS;
	}
}
