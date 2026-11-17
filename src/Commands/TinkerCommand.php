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
			return $this->failCommand($output, $asJson, 'TINKER_DISABLED', 'Tinker is disabled via PW_CLI_DISABLE_TINKER=1.');
		}

		$code = $input->getArgument('code');

		if ($code !== null && $code !== '') {
			try {
				$echoed = $this->runCode((string) $code, $asJson ? null : $output);
				if ($asJson) {
					return $this->outputJsonSuccess($output, ['output' => $echoed]);
				}
				return Command::SUCCESS;
			} catch (\Throwable $e) {
				return $this->failCommand($output, $asJson, 'TINKER_ERROR', $e->getMessage());
			}
		}

		if ($asJson || !$input->isInteractive()) {
			return $this->failCommand($output, $asJson, 'MISSING_CODE', 'Provide inline PHP code argument when running non-interactively.');
		}

		info('ProcessWire Tinker');
		note('Type PHP code and press enter. Type "exit" to quit.');
		note('Variables persist between lines. Classes/functions also persist (global scope).');
		note('Use: $pages, $user, $templates, $fields, $users, $roles, $config or wire("pages") etc.');

		while (true) {
			$line = text('>>>');
			if ($line === '' || in_array(strtolower(trim($line)), ['exit', 'quit', 'die'], true)) {
				break;
			}

			try {
				$this->runCode((string) $line, $output, true);
			} catch (\Throwable $e) {
				error($e->getMessage());
			}
		}

		return Command::SUCCESS;
	}

	/**
	 * Execute a single PHP code snippet with persisted variable state.
	 */
	private function runCode(string $code, ?OutputInterface $output, bool $interactiveEchoReturn = false): string
	{
		$apiVariables = [
			'pages',
			'user',
			'templates',
			'fields',
			'users',
			'roles',
			'permissions',
			'config',
			'modules',
			'database',
			'sanitizer',
			'session',
			'input',
			'log',
			'cache',
			'files',
			'datetime',
			'languages',
			'urls',
			'wire',
		];

		foreach ($apiVariables as $apiVar) {
			if (!isset(self::$state[$apiVar])) {
				$apiVal = \ProcessWire\wire($apiVar);
				if ($apiVal !== null) {
					self::$state[$apiVar] = $apiVal;
				}
			}
		}

		$isolatedEval = static function (string $snippet) use ($interactiveEchoReturn): array {
			extract(self::$state, EXTR_OVERWRITE | EXTR_REFS);

			$trimmed = rtrim(trim($snippet), ';');
			$canReturnExpr = $interactiveEchoReturn
				&& $trimmed !== ''
				&& !preg_match('/^(echo|print|return|if|for|foreach|while|do|switch|try|class|interface|trait|enum|function|namespace|use|unset|declare)\b/i', $trimmed)
				&& !str_contains($trimmed, ';');

			$evalSource = $canReturnExpr ? "return ({$trimmed});" : "{$trimmed};";
			$returnValue = null;

			ob_start();
			try {
				$returnValue = eval($evalSource);
			} finally {
				$echoed = (string) ob_get_clean();
			}

			$reserved = ['snippet', 'interactiveEchoReturn', 'trimmed', 'canReturnExpr', 'evalSource', 'returnValue', 'echoed', 'reserved', 'allNow', 'userVars', 'k', 'v'];
			$allNow = get_defined_vars();
			$userVars = [];
			foreach ($allNow as $k => $v) {
				if (in_array($k, $reserved, true)) {
					continue;
				}
				$userVars[$k] = $v;
			}

			return ['echoed' => $echoed, 'returnValue' => $returnValue, 'hasReturn' => $canReturnExpr, 'vars' => $userVars];
		};

		$result = $isolatedEval($code);
		self::$state = $result['vars'];

		$rendered = $result['echoed'];
		if ($rendered === '' && $result['hasReturn'] && $result['returnValue'] !== null) {
			if (is_scalar($result['returnValue'])) {
				$rendered = var_export($result['returnValue'], true) . "\n";
			} else {
				$encoded = json_encode($result['returnValue'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
				$rendered = ($encoded !== false ? $encoded : get_debug_type($result['returnValue'])) . "\n";
			}
		}

		if ($output !== null && $rendered !== '') {
			$output->write($rendered);
		}

		return $rendered;
	}
}
