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
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use function Laravel\Prompts\textarea;
use function Laravel\Prompts\warning;

final class PageUpdateCommand extends Command
{
	use InteractWithProcessWire;

	private const VALID_STATUSES = [
		'publish',
		'published',
		'unpublish',
		'unpublished',
		'hide',
		'hidden',
		'show',
		'lock',
		'locked',
		'unlock',
		'unlocked',
	];

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
			->addOption('set', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Set field value as key=value (repeatable)')
			->addOption('status', null, InputOption::VALUE_OPTIONAL, 'Optional status: published|unpublished|hidden|locked|unlocked|publish|unpublish|hide|show|lock|unlock')
			->addOption('interactive', 'i', InputOption::VALUE_NONE, 'Prompt for page and field values')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write changes')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$id = $input->getOption('id');
		$path = $input->getOption('path') !== null ? (string) $input->getOption('path') : null;
		$titleOpt = $input->getOption('title') !== null ? (string) $input->getOption('title') : null;
		$nameOpt = $input->getOption('name') !== null ? (string) $input->getOption('name') : null;
		$parentOpt = $input->getOption('parent') !== null ? trim((string) $input->getOption('parent')) : null;
		$templateOpt = $input->getOption('template') !== null ? trim((string) $input->getOption('template')) : null;
		$setRaw = $input->getOption('set');
		$statusOp = $input->getOption('status') ? strtolower(trim((string) $input->getOption('status'))) : '';
		$wantInteractive = (bool) $input->getOption('interactive');
		$dryRun = (bool) $input->getOption('dry-run');
		$asJson = (bool) $input->getOption('json');

		if ($statusOp !== '' && !in_array($statusOp, self::VALID_STATUSES, true)) {
			return $this->failCommand(
				$output,
				$asJson,
				'INVALID_STATUS',
				"Invalid --status '{$statusOp}'. Allowed values: " . implode(', ', self::VALID_STATUSES)
			);
		}

		if ($wantInteractive && $this->canPrompt($input, $asJson) && !$id && !$path) {
			$selectedId = (string) $this->searchPage('Select page to update');
			if ($selectedId === '') {
				return Command::SUCCESS;
			}
			$id = $selectedId;
		}

		if (!$id && !$path) {
			return $this->failCommand($output, $asJson, 'MISSING_IDENTIFIER', 'Provide --id or --path.');
		}

		$page = $this->resolvePage($id, $path);
		if ($page === null) {
			return $this->failCommand($output, $asJson, 'NOT_FOUND', 'Page not found.');
		}

		if ($this->isRbacPage($page)) {
			return $this->failCommand(
				$output,
				$asJson,
				'RBAC_PAGE_PROTECTED',
				'Use user:update, role:*, or permission:* commands to modify users, roles, or permissions.'
			);
		}

		$changes = $this->parseKeyValueSets($setRaw);
		unset(
			$changes['id'],
			$changes['name'],
			$changes['status'],
			$changes['statusPrevious'],
			$changes['flags'],
			$changes['pass'],
			$changes['roles'],
			$changes['permissions'],
			$changes['parent'],
			$changes['parent_id'],
			$changes['parentID'],
			$changes['parentPrevious'],
			$changes['template'],
			$changes['template_id'],
			$changes['templateID'],
			$changes['templatesID'],
			$changes['templates_id']
		);

		if ($titleOpt !== null) {
			$changes['title'] = $titleOpt;
		}
		if ($nameOpt !== null) {
			$cleanPageName = (string) \ProcessWire\wire('sanitizer')->pageName($nameOpt, true);
			if ($cleanPageName === '') {
				return $this->failCommand($output, $asJson, 'INVALID_NAME', "Invalid page name: '{$nameOpt}'.");
			}
			$changes['name'] = $cleanPageName;
		}

		$isProtected = $this->isProtectedSystemPage($page);
		if ($isProtected) {
			if (isset($changes['name']) || ($parentOpt !== null && $parentOpt !== '') || ($templateOpt !== null && $templateOpt !== '')) {
				return $this->failCommand($output, $asJson, 'SYSTEM_PAGE_PROTECTED', "Page #{$page->id} ({$page->path}) is a system page; its name, parent, and template cannot be changed.");
			}
			if (in_array($statusOp, ['unpublish', 'unpublished', 'hide', 'hidden'], true)) {
				return $this->failCommand($output, $asJson, 'SYSTEM_PAGE_PROTECTED', "Page #{$page->id} ({$page->path}) is a system page and cannot be unpublished or hidden.");
			}
		}

		$newParent = null;
		if ($parentOpt !== null && $parentOpt !== '') {
			$newParent = ctype_digit($parentOpt)
				? $this->resolvePage($parentOpt, null)
				: $this->resolvePage(null, $parentOpt);
			if ($newParent === null) {
				return $this->failCommand($output, $asJson, 'NOT_FOUND', "Parent not found: {$parentOpt}");
			}
			if ($newParent->isTrash()) {
				return $this->failCommand($output, $asJson, 'TRASH_PARENT_DISALLOWED', 'Use page:trash --force instead of moving a page into the Trash.');
			}
			if ($this->isRbacContainerPage($newParent)) {
				return $this->failCommand($output, $asJson, 'RBAC_CONTAINER_PROTECTED', 'Pages cannot be moved into RBAC system containers.');
			}
			if ((int) $newParent->id === (int) $page->id || $newParent->parents->has($page)) {
				return $this->failCommand($output, $asJson, 'INVALID_PARENT', 'Cannot move a page into itself or one of its descendants.');
			}
		}

		$newTemplate = null;
		if ($templateOpt !== null && $templateOpt !== '') {
			if (preg_match('/^[A-Za-z0-9_-]+$/', $templateOpt) !== 1) {
				return $this->failCommand($output, $asJson, 'INVALID_TEMPLATE', "Invalid template name: {$templateOpt}");
			}
			$newTemplate = \ProcessWire\wire('templates')->get($templateOpt);
			if (!$newTemplate || !$newTemplate->id) {
				return $this->failCommand($output, $asJson, 'NOT_FOUND', "Template not found: {$templateOpt}");
			}
			if ((((int) $newTemplate->flags) & \ProcessWire\Template::flagSystem) !== 0) {
				return $this->failCommand($output, $asJson, 'SYSTEM_TEMPLATE', "Template '{$templateOpt}' is a system template and cannot be assigned via page:update.");
			}
		}

		if ($wantInteractive && $this->canPrompt($input, $asJson)) {
			$statusChoices = $isProtected
				? ['none', 'publish', 'show', 'lock', 'unlock']
				: ['none', 'publish', 'unpublish', 'hide', 'show', 'lock', 'unlock'];
			if ($statusOp === '') {
				$sel = (string) select('Status change', $statusChoices, default: 'none');
				$statusOp = $sel !== 'none' ? $sel : '';
			}
			$fill = confirm('Edit template fields now?', default: false);
			if ($fill && $page->template instanceof \ProcessWire\Template) {
				$sanitizer = \ProcessWire\wire('sanitizer');
				foreach ($page->template->fieldgroup as $f) {
					$fname = (string) $f->name;
					$ftype = $f->type;
					$label = $f->label ?: $fname;
					$desc = $f->description ?: '';
					$prompt = trim($label . ($desc ? " — {$desc}" : ''));
					$current = (string) $page->getUnformatted($fname);
					$val = null;
					if ($ftype instanceof \ProcessWire\FieldtypeTextarea) {
						$val = textarea($prompt, default: $current, required: false);
						$val = $sanitizer->textarea($val);
					} elseif ($ftype instanceof \ProcessWire\FieldtypeText) {
						$val = text($prompt, default: $current, required: false);
						$val = $sanitizer->text($val);
					}
					if ($val !== null && $val !== $current) {
						$changes[$fname] = $val;
					}
				}
			}
		}

		$effectiveTpl = $newTemplate instanceof \ProcessWire\Template
			? $newTemplate
			: ($page->template instanceof \ProcessWire\Template ? $page->template : null);
		if (in_array($statusOp, ['unpublish', 'unpublished'], true) && $effectiveTpl !== null && !empty($effectiveTpl->noUnpublish)) {
			return $this->failCommand($output, $asJson, 'UNPUBLISH_FORBIDDEN', "Template '{$effectiveTpl->name}' forbids unpublishing pages (noUnpublish=1).");
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

		$page->of(false);

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
				if ($asJson) {
					return $this->failCommand($output, true, 'INVALID_FIELD_VALUE', "Field '{$k}': " . $e->getMessage());
				}
				warning("Field '{$k}': " . $e->getMessage());
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

		try {
			$page->save();
		} catch (\Throwable $e) {
			return $this->failCommand($output, $asJson, 'SAVE_FAILED', $e->getMessage());
		}

		$result['path'] = (string) $page->path;
		if ($asJson) {
			return $this->outputJsonSuccess($output, $result + ['saved' => true]);
		}

		info("Updated page #{$page->id}.");
		return Command::SUCCESS;
	}
}
