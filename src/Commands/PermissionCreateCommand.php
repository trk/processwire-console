<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Traits\InteractWithProcessWire;
use function Laravel\Prompts\info;
use function Laravel\Prompts\text;

final class PermissionCreateCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('permission:create')
			->setDescription('Create a new permission.')
			->addArgument('name', InputArgument::OPTIONAL, 'The name of the permission')
			->addArgument('title', InputArgument::OPTIONAL, 'The title of the permission')
			->addOption('name', null, InputOption::VALUE_OPTIONAL, 'The name of the permission')
			->addOption('title', null, InputOption::VALUE_OPTIONAL, 'The title of the permission')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$asJson = (bool) $input->getOption('json');
		$name = $input->getOption('name') ?: $input->getArgument('name');
		$title = $input->getOption('title') ?: $input->getArgument('title');

		if (!$name && $this->canPrompt($input, $asJson)) {
			$name = text('Permission name', required: true);
			if (!$title) {
				$title = text('Permission title (optional)', required: false);
			}
		}

		if (!$name) {
			return $this->failCommand($output, $asJson, 'MISSING_NAME', 'Provide permission name via --name or argument.');
		}

		$rawName = trim((string) $name);
		$cleanName = (string) \ProcessWire\wire('sanitizer')->pageName($rawName, true);
		if ($cleanName === '' || $cleanName !== strtolower($rawName) || ctype_digit($cleanName)) {
			return $this->failCommand($output, $asJson, 'INVALID_NAME', "Invalid permission name '{$rawName}'. Must be a valid non-numeric ProcessWire page name.");
		}

		$existing = $this->findNamedSystemPage('permissions', $cleanName);
		if ($existing !== null) {
			return $this->failCommand($output, $asJson, 'ALREADY_EXISTS', "Permission '{$cleanName}' already exists.");
		}

		$p = \ProcessWire\wire('permissions')->add($cleanName);
		$p->of(false);
		if ($title) {
			$p->title = (string) $title;
		}
		$p->save();

		if ($asJson) {
			return $this->outputJsonSuccess($output, [
				'id' => (int) $p->id,
				'name' => (string) $p->name,
				'title' => (string) ($p->title ?: ''),
			]);
		}

		info("Permission '{$p->name}' created.");

		return Command::SUCCESS;
	}
}
