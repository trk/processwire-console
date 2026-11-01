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
use function Laravel\Prompts\text;

final class TemplateFieldsReorderCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('template:fields:reorder')
			->setDescription('Reorder fields on a template (moves listed fields in given order to the front).')
			->addOption('template', null, InputOption::VALUE_OPTIONAL, 'Template name')
			->addOption('fields', null, InputOption::VALUE_OPTIONAL, 'Comma-separated field names in desired order (front)')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write changes')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$templateName = $input->getOption('template');
		$asJson = (bool) $input->getOption('json');

		if (!$templateName && !$asJson) {
			$templateName = $this->searchTemplate('Select the template to reorder fields');
			if ($templateName === '') {
				return Command::SUCCESS;
			}
		}

		if (!$templateName) {
			$msg = 'Provide --template.';
			return $asJson ? $this->outputJsonError($output, 'MISSING_TEMPLATE', $msg) : (error($msg) ?? Command::FAILURE);
		}

		$orderRaw = $input->getOption('fields');
		$orderStr = $orderRaw ? (string) $orderRaw : '';
		if (!$orderStr && !$asJson) {
			$orderStr = text(
				label: 'Enter comma-separated field names in desired order',
				required: true
			);
		}

		$dryRun = (bool) $input->getOption('dry-run');

		if (!$orderStr) {
			$msg = 'Provide --fields.';
			return $asJson ? $this->outputJsonError($output, 'MISSING_FIELDS', $msg) : (error($msg) ?? Command::FAILURE);
		}

		$templates = \ProcessWire\wire('templates');
		$template = $templates->get((string) $templateName);
		if (!$template || !$template->id) {
			$msg = "Template not found: {$templateName}";
			return $asJson ? $this->outputJsonError($output, 'NOT_FOUND', $msg) : (error($msg) ?? Command::FAILURE);
		}
		$fg = $template->fieldgroup;
		$names = array_map('trim', explode(',', $orderStr));

		$valid = [];
		foreach ($names as $n) {
			if ($n === '') {
				continue;
			}
			$f = $fg->getField($n);
			if ($f) {
				$valid[] = $n;
			}
		}

		$result = ['template' => $templateName, 'order' => $valid, 'dryRun' => $dryRun];
		if ($dryRun) {
			if ($asJson) {
				return $this->outputJsonSuccess($output, $result);
			}
			note('Dry-run: would move fields to front in order: ' . implode(', ', $valid));
			return Command::SUCCESS;
		}

		foreach (array_reverse($valid) as $n) {
			$f = $fg->getField($n);
			if (!$f) {
				continue;
			}
			$fg->remove($f);
			$fg->add($f);
			$first = $fg->first();
			if ($first && $first !== $f) {
				$fg->insertBefore($f, $first);
			}
		}

		$fg->save();

		if ($asJson) {
			return $this->outputJsonSuccess($output, $result + ['reordered' => true]);
		}

		info("Reordered fields on template '{$templateName}'.");
		return Command::SUCCESS;
	}
}
