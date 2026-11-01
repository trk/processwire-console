<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Traits\InteractWithProcessWire;
use function Laravel\Prompts\error;
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

		if (!$name && $input->isInteractive() && !$asJson) {
			$name = text('Permission name', required: true);
			if (!$title) {
				$title = text('Permission title (optional)', required: false);
			}
		}

		if (!$name) {
			$msg = 'Provide permission name via --name or argument.';
			return $asJson ? $this->outputJsonError($output, 'MISSING_NAME', $msg) : (error($msg) ?? Command::FAILURE);
		}

		$existing = \ProcessWire\wire('permissions')->get((string) $name);
		if ($existing && $existing->id) {
			$msg = "Permission '{$name}' already exists.";
			return $asJson ? $this->outputJsonError($output, 'ALREADY_EXISTS', $msg) : (error($msg) ?? Command::FAILURE);
		}

		$p = \ProcessWire\wire('permissions')->add((string) $name);
		if ($title) {
			$p->title = (string) $title;
		}
		$p->save();

		if ($asJson) {
			return $this->outputJsonSuccess($output, [
				'id' => $p->id,
				'name' => $p->name,
				'title' => (string) ($p->title ?: ''),
			]);
		}

		info("Permission '{$name}' created.");

		return Command::SUCCESS;
	}
}
