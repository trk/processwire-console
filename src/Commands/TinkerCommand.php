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
                ob_start();
                eval($code . ';');
                $result = ob_get_clean() ?: '';
                $output->writeln($result);
                return Command::SUCCESS;
            } catch (\Throwable $e) {
                error($e->getMessage());
                return Command::FAILURE;
            }
        }

        info('ProcessWire Tinker');
        note('Type PHP code and press enter. Type "exit" to quit.');

        while (true) {
            $line = text('>>>');
            if ($line === null || $line === '' || in_array(strtolower($line), ['exit', 'quit', 'die'])) {
                break;
            }

            try {
                ob_start();
                eval($line . ';');
                $result = ob_get_clean() ?: '';
                $output->writeln($result);
            } catch (\Throwable $e) {
                error($e->getMessage());
            }
        }

        return Command::SUCCESS;
    }
}
