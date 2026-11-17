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
		$rawId = $input->getOption('id');
		$idStr = ($rawId !== null && $rawId !== '') ? trim((string) $rawId) : '';
		$username = $input->getOption('name') ? trim((string) $input->getOption('name')) : '';
		$dryRun = (bool) $input->getOption('dry-run');
		$asJson = (bool) $input->getOption('json');
		$force = (bool) $input->getOption('force');

		if ($idStr === '' && $username === '' && $this->canPrompt($input, $asJson, $force)) {
			$username = $this->searchUser('Select a user to delete');
			if ($username === '') {
				return Command::SUCCESS;
			}
		}

		if ($idStr === '' && $username === '') {
			return $this->failCommand($output, $asJson, 'MISSING_IDENTIFIER', 'Provide --id or --name.');
		}

		$users = \ProcessWire\wire('users');
		if ($idStr !== '') {
			if (!ctype_digit($idStr)) {
				return $this->failCommand($output, $asJson, 'INVALID_ID', 'Option --id must be a positive integer.');
			}
			$user = $users->get((int) $idStr);
		} else {
			$user = $this->findNamedSystemPage('users', $username);
		}

		if (!($user instanceof \ProcessWire\User) || !$user->id) {
			return $this->failCommand($output, $asJson, 'NOT_FOUND', 'User not found.');
		}
		if ($user->isSuperuser()) {
			return $this->failCommand($output, $asJson, 'SUPERUSER_PROTECTED', 'Refusing to delete a superuser.');
		}
		if ((string) $user->name === 'guest') {
			return $this->failCommand($output, $asJson, 'GUEST_PROTECTED', 'Refusing to delete the built-in guest user.');
		}

		if (!$force && !$dryRun) {
			if ($asJson || !$input->isInteractive()) {
				return $this->failCommand($output, $asJson, 'CONFIRMATION_REQUIRED', 'Use --force to run user:delete non-interactively.');
			}
			if (!confirm("Delete user '{$user->name}' (ID {$user->id})?", false)) {
				note('Aborted.');
				return Command::SUCCESS;
			}
		}

		$result = ['id' => (int) $user->id, 'name' => (string) $user->name, 'dryRun' => $dryRun];
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
			return $this->failCommand($output, $asJson, 'DELETE_FAILED', "Delete failed for user '{$user->name}': " . $e->getMessage());
		}

		if ($asJson) {
			return $this->outputJsonSuccess($output, $result + ['deleted' => true]);
		}

		info("Deleted user '{$user->name}'.");
		return Command::SUCCESS;
	}
}
