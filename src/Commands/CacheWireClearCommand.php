<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Traits\InteractWithProcessWire;
use function Laravel\Prompts\info;

final class CacheWireClearCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('cache:wire:clear')
			->setDescription('Clear WireCache by key or pattern.')
			->addOption('key', null, InputOption::VALUE_REQUIRED, 'Exact cache key to delete')
			->addOption('pattern', null, InputOption::VALUE_REQUIRED, 'SQL LIKE pattern for names (e.g., "Template%")')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$rawKey = $input->getOption('key');
		$rawPattern = $input->getOption('pattern');
		$key = ($rawKey !== null && $rawKey !== '') ? (string) $rawKey : '';
		$pattern = ($rawPattern !== null && $rawPattern !== '') ? (string) $rawPattern : '';
		$asJson = (bool) $input->getOption('json');

		if ($key === '' && $pattern === '') {
			return $this->failCommand($output, $asJson, 'MISSING_OPTIONS', 'Provide --key or --pattern.');
		}

		if ($key !== '') {
			$deleted = \ProcessWire\wire('cache')->delete($key) ? 1 : 0;
		} else {
			$db = \ProcessWire\wire('database');
			$table = '`' . $db->escapeTable('caches') . '`';
			$stmt = $db->prepare("DELETE FROM {$table} WHERE name LIKE :p");
			$stmt->bindValue(':p', $pattern);
			$stmt->execute();
			$deleted = $stmt->rowCount();
		}

		$data = ['deleted' => $deleted, 'key' => $key, 'pattern' => $pattern];
		if ($asJson) {
			return $this->outputJsonSuccess($output, $data);
		}

		info("Deleted {$deleted} cache entr" . ($deleted === 1 ? 'y' : 'ies') . '.');
		return Command::SUCCESS;
	}
}
