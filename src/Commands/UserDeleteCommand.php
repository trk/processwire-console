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

final class UserDeleteCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('user:delete')
			->setDescription('Delete a user.')
			->addOption('id', null, InputOption::VALUE_OPTIONAL, 'User ID')
			->addOption('name', null, InputOption::VALUE_OPTIONAL, 'User name')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write changes')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output')
			->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip interactive confirmations');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$id = $input->getOption('id');
		$username = $input->getOption('name') ? (string) $input->getOption('name') : '';
		$dryRun = (bool) $input->getOption('dry-run');
		$asJson = (bool) $input->getOption('json');
		$force = (bool) $input->getOption('force');

		if (!$id && !$username && !$asJson) {
			$username = $this->searchUser('Select a user to delete');
			if ($username === '') {
				return Command::SUCCESS;
			}
		}

		if (!$id && !$username) {
			$msg = 'Provide --id or --name.';
			return $asJson ? $this->outputJsonError($output, 'MISSING_IDENTIFIER', $msg) : (error($msg) ?? Command::FAILURE);
		}

		$users = \ProcessWire\wire('users');
		$user = $id ? $users->get((int) $id) : $users->get((string) $username);
		if (!$user || !$user->id) {
			$msg = 'User not found.';
			return $asJson ? $this->outputJsonError($output, 'NOT_FOUND', $msg) : (error($msg) ?? Command::FAILURE);
		}
		if ($user->isSuperuser()) {
			$msg = 'Refusing to delete a superuser.';
			return $asJson ? $this->outputJsonError($output, 'SUPERUSER_PROTECTED', $msg) : (error($msg) ?? Command::FAILURE);
		}

		if (!$force && !$dryRun) {
			if ($asJson) {
				return $this->outputJsonError($output, 'CONFIRMATION_REQUIRED', 'Use --force to run user:delete non-interactively with --json.');
			}
			if (!confirm("Delete user '{$user->name}' (ID {$user->id})?", false)) {
				note('Aborted.');
				return Command::SUCCESS;
			}
		}

		$result = ['id' => $user->id, 'name' => $user->name, 'dryRun' => $dryRun];
		if ($dryRun) {
			if ($asJson) {
				return $this->outputJsonSuccess($output, $result);
			}
			note("Dry-run: would delete user '{$user->name}'.");
			return Command::SUCCESS;
		}

		try {
			$users->delete($user);
		} catch (\Throwable $e) {
			$msg = "Delete failed for user '{$user->name}': " . $e->getMessage();
			return $asJson ? $this->outputJsonError($output, 'DELETE_FAILED', $msg) : (error($msg) ?? Command::FAILURE);
		}

		if ($asJson) {
			return $this->outputJsonSuccess($output, $result + ['deleted' => true]);
		}

		info("Deleted user '{$user->name}'.");
		return Command::SUCCESS;
	}
}
