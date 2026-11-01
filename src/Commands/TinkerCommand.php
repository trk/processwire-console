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
use function Laravel\Prompts\note;
use function Laravel\Prompts\text;

final class TinkerCommand extends Command
{
	use InteractWithProcessWire;

	/**
	 * @var array<string, mixed> Persisted variable state across eval() lines.
	 */
	private static array $state = [];

	protected function configure(): void
	{
		$this
			->setName('tinker')
			->setDescription('Interactive shell for ProcessWire.')
			->addArgument('code', InputArgument::OPTIONAL, 'PHP code to run directly')
			->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$asJson = (bool) $input->getOption('json');

		$disabled = getenv('PW_CLI_DISABLE_TINKER') === '1'
			|| (string) ($_ENV['PW_CLI_DISABLE_TINKER'] ?? '') === '1';

		if ($disabled) {
			$msg = 'Tinker is disabled via PW_CLI_DISABLE_TINKER=1.';
			if ($asJson) {
				return $this->outputJsonError($output, 'TINKER_DISABLED', $msg);
			}
			error($msg);
			return Command::FAILURE;
		}

		$code = $input->getArgument('code');

		if ($code) {
			try {
				$echoed = $this->runCode((string) $code, $asJson ? null : $output);
				if ($asJson) {
					return $this->outputJsonSuccess($output, ['output' => $echoed]);
				}
				return Command::SUCCESS;
			} catch (\Throwable $e) {
				if ($asJson) {
					return $this->outputJsonError($output, 'TINKER_ERROR', $e->getMessage());
				}
				error($e->getMessage());
				return Command::FAILURE;
			}
		}

		if ($asJson) {
			return $this->outputJsonError($output, 'MISSING_CODE', 'Provide inline PHP code argument when using --json.');
		}

		info('ProcessWire Tinker');
		note('Type PHP code and press enter. Type "exit" to quit.');
		note('Variables persist between lines. Classes/functions also persist (global scope).');
		note('Use: $pages, $templates, $fields, $users, $roles, $config or wire("pages") etc.');

		while (true) {
			$line = text('>>>');
			if ($line === '' || in_array(strtolower(trim($line)), ['exit', 'quit', 'die'], true)) {
				break;
			}

			try {
				$this->runCode((string) $line, $output);
			} catch (\Throwable $e) {
				error($e->getMessage());
			}
		}

		return Command::SUCCESS;
	}

	/**
	 * Execute a single PHP code snippet with persisted variable state.
	 */
	private function runCode(string $code, ?OutputInterface $output): string
	{
		foreach (['pages', 'templates', 'fields', 'users', 'roles', 'permissions', 'config', 'modules', 'database', 'sanitizer', 'session', 'input', 'log', 'cache', 'wire'] as $apiVar) {
			if (!isset(self::$state[$apiVar])) {
				$apiVal = \ProcessWire\wire($apiVar);
				if ($apiVal !== null) {
					self::$state[$apiVar] = $apiVal;
				}
			}
		}

		$isolatedEval = function (string $code): array {
			extract(self::$state, EXTR_OVERWRITE | EXTR_REFS);

			ob_start();
			eval($code . ';');
			$echoed = (string) ob_get_clean();

			$reserved = ['code', 'echoed', 'reserved', 'allNow', 'userVars', 'k', 'v'];
			$allNow = get_defined_vars();
			$userVars = [];
			foreach ($allNow as $k => $v) {
				if (in_array($k, $reserved, true)) {
					continue;
				}
				$userVars[$k] = $v;
			}

			return ['echoed' => $echoed, 'vars' => $userVars];
		};

		$result = $isolatedEval($code);
		self::$state = $result['vars'];

		if ($output !== null && $result['echoed'] !== '') {
			$output->write($result['echoed']);
		}

		return $result['echoed'];
	}
}
