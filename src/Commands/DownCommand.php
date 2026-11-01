<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Traits\InteractWithProcessWire;

final class DownCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('down')
			->setDescription('Put the application into maintenance / demo mode')
			->addOption('redirect', null, InputOption::VALUE_REQUIRED, 'The URI that users should be redirected to')
			->addOption('retry', null, InputOption::VALUE_REQUIRED, 'The number of seconds after which the request may be retried')
			->addOption('secret', null, InputOption::VALUE_REQUIRED, 'The secret phrase that may be used to bypass maintenance mode')
			->addOption('status', null, InputOption::VALUE_REQUIRED, 'The status code that should be used when returning the maintenance mode response', 503)
			->addOption('json', null, InputOption::VALUE_NONE, 'Output results as JSON');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$json = (bool) $input->getOption('json');
		$wire = \ProcessWire\wire();
		if (!$wire) {
			if ($json) {
				return $this->outputJsonError($output, 'ENV_NOT_FOUND', 'ProcessWire environment not found.');
			}
			\Laravel\Prompts\error('ProcessWire environment not found.');
			return Command::FAILURE;
		}

		try {
			$file = $wire->config->paths->site . 'down.json';

			if (file_exists($file)) {
				if ($json) {
					return $this->outputJsonSuccess($output, ['message' => 'Application is already down.', 'file' => $file]);
				}
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
			@chmod($file, 0600);

			if ($json) {
				return $this->outputJsonSuccess($output, [
					'message' => 'Application is now in maintenance mode.',
					'file' => $file,
					'payload' => $payload,
				]);
			}

			\Laravel\Prompts\info('Application is now in maintenance mode.');
			
			\Laravel\Prompts\note(
				"Note: The maintenance flag file is stored at site/down.json (NOT web-accessible).\n" .
				"Make sure your site/init.php checks for this file and handles the response. \n" .
				"Example snippet for site/init.php:\n" .
				"if (file_exists(\$config->paths->site . 'down.json')) {\n" . 
				"    \$down = json_decode(file_get_contents(\$config->paths->site . 'down.json'), true);\n" . 
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
			if ($json) {
				return $this->outputJsonError($output, 'MAINTENANCE_ERROR', 'Failed to enter maintenance mode: ' . $e->getMessage());
			}
			\Laravel\Prompts\error('Failed to enter maintenance mode: ' . $e->getMessage());
			return Command::FAILURE;
		}
	}
}
