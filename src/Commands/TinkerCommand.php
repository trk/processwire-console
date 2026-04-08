<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

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
        $io = new SymfonyStyle($input, $output);
        $code = $input->getArgument('code');

        if ($code) {
            try {
                ob_start();
                eval($code . ';');
                $result = ob_get_clean() ?: '';
                $output->writeln($result);
                return Command::SUCCESS;
            } catch (\Throwable $e) {
                $io->error($e->getMessage());
                return Command::FAILURE;
            }
        }

        $io->title('ProcessWire Tinker');
        $io->note('Type PHP code and press enter. Type "exit" to quit.');

        while (true) {
            $line = $io->ask('>>> ');
            if ($line === null || in_array(strtolower($line), ['exit', 'quit', 'die'])) {
                break;
            }

            try {
                ob_start();
                eval($line . ';');
                $result = ob_get_clean() ?: '';
                $output->writeln($result);
            } catch (\Throwable $e) {
                $io->error($e->getMessage());
            }
        }

        return Command::SUCCESS;
    }
}
