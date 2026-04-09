<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class DownCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('down')
            ->setDescription('Put the application into maintenance / demo mode')
            ->addOption('redirect', null, InputOption::VALUE_REQUIRED, 'The URI that users should be redirected to')
            ->addOption('retry', null, InputOption::VALUE_REQUIRED, 'The number of seconds after which the request may be retried')
            ->addOption('secret', null, InputOption::VALUE_REQUIRED, 'The secret phrase that may be used to bypass maintenance mode')
            ->addOption('status', null, InputOption::VALUE_REQUIRED, 'The status code that should be used when returning the maintenance mode response', 503);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $wire = \ProcessWire\wire();
        if (!$wire) {
            \Laravel\Prompts\error("ProcessWire environment not found.");
            return Command::FAILURE;
        }

        try {
            $file = $wire->config->paths->assets . 'down.json';

            if (file_exists($file)) {
                \Laravel\Prompts\warning('Application is already down.');
                return Command::SUCCESS;
            }

            $payload = [
                'redirect' => $input->getOption('redirect'),
                'retry' => $input->getOption('retry'),
                'secret' => $input->getOption('secret'),
                'status' => (int) $input->getOption('status'),
                'time' => time(),
            ];

            file_put_contents($file, json_encode($payload, JSON_PRETTY_PRINT));

            \Laravel\Prompts\info('Application is now in maintenance mode.');
            
            \Laravel\Prompts\note(
                "Note: Make sure your site/init.php checks for this file and handles the response. \n" .
                "Example snippet for site/init.php:\n" .
                "if (file_exists(\$config->paths->assets . 'down.json')) {\n" . 
                "    \$down = json_decode(file_get_contents(\$config->paths->assets . 'down.json'), true);\n" . 
                "    // verify \$down['secret'] from \$_GET or \$_COOKIE, otherwise exit / redirect\n" . 
                "    if(empty(\$down['secret']) || \$input->get('secret') !== \$down['secret']) {\n" . 
                "       if(!empty(\$down['redirect'])) \$session->redirect(\$down['redirect']);\n" .
                "       http_response_code(\$down['status'] ?? 503);\n" .
                "       die('Site is under maintenance.');\n" . 
                "    }\n" . 
                "}"
            );

            return Command::SUCCESS;

        } catch (\Exception $e) {
            \Laravel\Prompts\error('Failed to enter maintenance mode: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
