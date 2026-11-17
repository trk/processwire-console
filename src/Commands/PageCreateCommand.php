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
use function Laravel\Prompts\text;
use function Laravel\Prompts\textarea;

final class PageCreateCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('page:create')
			->setDescription('Create a new page under a parent with a given template.')
			->addOption('parent', 'p', InputOption::VALUE_OPTIONAL, 'Parent page path or ID')
			->addOption('template', 't', InputOption::VALUE_OPTIONAL, 'Template name')
			->addOption('name', null, InputOption::VALUE_OPTIONAL, 'Page name (optional, derived from title if omitted)')
			->addOption('title', null, InputOption::VALUE_OPTIONAL, 'Page title (optional)')
			->addOption('unpublished', null, InputOption::VALUE_NONE, 'Create as unpublished')
			->addOption('interactive', 'i', InputOption::VALUE_NONE, 'Prompt for missing values and field inputs')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write changes, only show what would happen')
			->addOption('json', null, InputOption::VALUE_NONE, 'Output machine-readable JSON');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$parentArg = trim((string) $input->getOption('parent'));
		$templateName = trim((string) $input->getOption('template'));
		$name = $input->getOption('name') ? (string) $input->getOption('name') : null;
		$title = $input->getOption('title') ? (string) $input->getOption('title') : null;
		$unpublished = (bool) $input->getOption('unpublished');
		$wantInteractive = (bool) $input->getOption('interactive');
		$dryRun = (bool) $input->getOption('dry-run');
		$asJson = (bool) $input->getOption('json');

		$pages = \ProcessWire\wire('pages');
		$templates = \ProcessWire\wire('templates');
		$sanitizer = \ProcessWire\wire('sanitizer');

		if (($wantInteractive || $parentArg === '' || $templateName === '') && $this->canPrompt($input, $asJson)) {
			if ($templateName === '') {
				$templateName = $this->searchTemplate('Select template');
				if ($templateName === '') {
					return Command::SUCCESS;
				}
			}
			if ($parentArg === '') {
				$parentArg = (string) $this->searchPage('Select parent page');
				if ($parentArg === '') {
					return Command::SUCCESS;
				}
			}
			if ($title === null) {
				$title = text('Title', required: false);
			}
			if ($name === null) {
				$defaultName = $title ? $sanitizer->pageName($title, true) : null;
				$name = text('Name', default: $defaultName ?? '', required: false);
				if ($name === '' && $defaultName) {
					$name = $defaultName;
				}
			}
		}

		if ($parentArg === '' || $templateName === '') {
			return $this->failCommand($output, $asJson, 'MISSING_OPTIONS', 'Provide --parent and --template.');
		}

		if (preg_match('/^[A-Za-z0-9_-]+$/', $templateName) !== 1) {
			return $this->failCommand($output, $asJson, 'INVALID_TEMPLATE', "Invalid template name: {$templateName}");
		}

		$parent = ctype_digit($parentArg)
			? $this->resolvePage($parentArg, null)
			: $this->resolvePage(null, $parentArg);
		if ($parent === null) {
			return $this->failCommand($output, $asJson, 'NOT_FOUND', "Parent not found: {$parentArg}");
		}

		$template = $templates->get($templateName);
		if (!$template || !$template->id) {
			return $this->failCommand($output, $asJson, 'NOT_FOUND', "Template not found: {$templateName}");
		}

		if (!$name && $title) {
			$name = $sanitizer->pageName($title, true);
		}
		if (!$name) {
			$name = 'page-' . date('Ymd-His');
		}
		$name = (string) $sanitizer->pageName($name, true);
		if ($name === '') {
			return $this->failCommand($output, $asJson, 'INVALID_NAME', 'Page name sanitized to an empty string.');
		}

		$result = [
			'parent' => $parent->path,
			'template' => $templateName,
			'name' => $name,
			'title' => $title ?? '',
			'status' => $unpublished ? 'unpublished' : 'published',
			'dryRun' => $dryRun,
		];

		if ($dryRun) {
			if ($asJson) {
				return $this->outputJsonSuccess($output, $result);
			}
			note("Dry-run: would create page '{$name}' under '{$parent->path}' with template '{$templateName}'.");
			return Command::SUCCESS;
		}

		$page = $pages->newPage(['template' => $template]);
		$page->of(false);
		$page->parent = $parent;
		$page->name = $name;
		if ($title !== null && $title !== '') {
			$page->title = $title;
		}
		if ($unpublished) {
			$page->addStatus(\ProcessWire\Page::statusUnpublished);
		}
		if (($wantInteractive || (!$input->getOption('title') && !$input->getOption('name'))) && $this->canPrompt($input, $asJson)) {
			$fill = confirm('Fill template fields now?', default: false);
			if ($fill) {
				foreach ($template->fieldgroup as $f) {
					$fname = (string) $f->name;
					if ($fname === 'title') {
						continue;
					}
					$ftype = $f->type;
					$label = $f->label ?: $fname;
					$desc = $f->description ?: '';
					$prompt = trim($label . ($desc ? " — {$desc}" : ''));
					$val = null;
					if ($ftype instanceof \ProcessWire\FieldtypeTextarea) {
						$val = textarea($prompt, required: false);
						$val = $sanitizer->textarea($val);
					} elseif ($ftype instanceof \ProcessWire\FieldtypeText) {
						$val = text($prompt, required: false);
						$val = $sanitizer->text($val);
					}
					if ($val !== null && $val !== '') {
						$page->set($fname, $val);
					}
				}
			}
		}

		try {
			$page->save();
		} catch (\Throwable $e) {
			return $this->failCommand($output, $asJson, 'SAVE_FAILED', $e->getMessage());
		}

		$result['id'] = $page->id;
		$result['url'] = $page->url;

		if ($asJson) {
			return $this->outputJsonSuccess($output, $result);
		}

		info("Created page #{$page->id} at {$page->url} (" . ($unpublished ? 'unpublished' : 'published') . ').');
		return Command::SUCCESS;
	}
}
