<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function Laravel\Prompts\text;
use function Laravel\Prompts\error;
use function Laravel\Prompts\note;
use function Laravel\Prompts\info;

final class TinkerCommand extends Command
{
    /**
     * @var array<string, mixed> Persisted variable state across eval() lines.
     */
    private static array $state = [];

    protected function configure(): void
    {
        $this
            ->setName('tinker')
            ->setDescription('Interactive shell for ProcessWire.')
            ->addArgument('code', InputArgument::OPTIONAL, 'PHP code to run directly');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $code = $input->getArgument('code');

        if ($code) {
            try {
                $this->runCode((string)$code, $output);
                return Command::SUCCESS;
            } catch (\Throwable $e) {
                error($e->getMessage());
                return Command::FAILURE;
            }
        }

        info('ProcessWire Tinker');
        note('Type PHP code and press enter. Type "exit" to quit.');
        note('Variables persist between lines. Classes/functions also persist (global scope).');
        note('Use: $pages, $templates, $fields, $users, $roles, $config or wire("pages") etc.');

        while (true) {
            $line = text('>>>');
            if ($line === null || $line === '' || in_array(strtolower(trim($line)), ['exit', 'quit', 'die'])) {
                break;
            }

            try {
                $this->runCode((string)$line, $output);
            } catch (\Throwable $e) {
                error($e->getMessage());
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Execute a single PHP code snippet with persisted variable state.
     *
     * Variables from previous runs are extracted into the eval scope, and any
     * newly defined / modified variables are captured back into self::$state.
     * Classes, interfaces, traits and functions defined in eval() are global
     * in PHP and persist automatically across separate eval() calls.
     */
    private function runCode(string $code, OutputInterface $output): void
    {
        // ProcessWire API variables into state (only first time — they're singletons anyway)
        foreach (['pages', 'templates', 'fields', 'users', 'roles', 'permissions', 'config', 'modules', 'database', 'sanitizer', 'session', 'input', 'log', 'cache', 'wire'] as $apiVar) {
            if (!isset(self::$state[$apiVar])) {
                $apiVal = \ProcessWire\wire($apiVar);
                if ($apiVal !== null) {
                    self::$state[$apiVar] = $apiVal;
                }
            }
        }

        // Use an isolated anonymous closure to protect this method's local scope,
        // but allow variable persistence through self::$state.
        $isolatedEval = function (string $code): array {
            extract(self::$state, EXTR_OVERWRITE | EXTR_REFS);

            ob_start();
            eval($code . ';');
            $echoed = (string)ob_get_clean();

            // Capture all variables defined or changed inside the eval scope
            // (except the ones we introduced here)
            $reserved = ['code', 'echoed', 'reserved', 'allNow', 'userVars', 'k', 'v'];
            $allNow = get_defined_vars();
            $userVars = [];
            foreach ($allNow as $k => $v) {
                if (in_array($k, $reserved, true)) continue;
                $userVars[$k] = $v;
            }

            return ['echoed' => $echoed, 'vars' => $userVars];
        };

        $result = $isolatedEval($code);
        self::$state = $result['vars'];

        if ($result['echoed'] !== '') {
            $output->write($result['echoed']);
        }
    }
}
