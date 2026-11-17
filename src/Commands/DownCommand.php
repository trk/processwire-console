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
			->addOption('status', null, InputOption::VALUE_REQUIRED, 'The HTTP status code (100-599) returned during maintenance mode', 503)
			->addOption('json', null, InputOption::VALUE_NONE, 'Output results as JSON');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$json = (bool) $input->getOption('json');
		$wire = \ProcessWire\wire();
		if (!$wire) {
			return $this->failCommand($output, $json, 'ENV_NOT_FOUND', 'ProcessWire environment not found.');
		}

		$statusCode = (int) $input->getOption('status');
		if ($statusCode < 100 || $statusCode > 599) {
			return $this->failCommand($output, $json, 'INVALID_STATUS', 'HTTP status code must be between 100 and 599.');
		}

		try {
			$file = (string) $wire->config->paths->site . 'down.json';

			if (is_link($file)) {
				throw new \RuntimeException('Refusing to write maintenance flag through a symbolic link.');
			}

			if (file_exists($file)) {
				if ($json) {
					return $this->outputJsonSuccess($output, ['message' => 'Application is already down.', 'file' => $file]);
				}
				\Laravel\Prompts\warning('Application is already down.');
				return Command::SUCCESS;
			}

			$retryOpt = $input->getOption('retry');
			$payload = [
				'redirect' => $input->getOption('redirect'),
				'retry' => ($retryOpt !== null && $retryOpt !== '') ? max(0, (int) $retryOpt) : null,
				'secret' => $input->getOption('secret'),
				'status' => $statusCode,
				'time' => time(),
			];

			$encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
			if ($encoded === false || file_put_contents($file, $encoded) === false) {
				throw new \RuntimeException("Unable to write {$file}.");
			}
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
				"Note: The maintenance flag file is stored at site/down.json (ensure your web server blocks direct access to /site/*.json).\n"
				. "Add an early check in site/init.php (guarding PHP_SAPI !== 'cli' so 'wire up' keeps working):\n"
				. "\$downFile = \$config->paths->site . 'down.json';\n"
				. "if (is_file(\$downFile) && PHP_SAPI !== 'cli') {\n"
				. "\t\$down = json_decode((string) file_get_contents(\$downFile), true) ?: [];\n"
				. "\t\$secret = (string) (\$down['secret'] ?? '');\n"
				. "\tif (\$secret === '' || !hash_equals(\$secret, (string) \$input->get('secret'))) {\n"
				. "\t\tif (!empty(\$down['redirect'])) {\n"
				. "\t\t\t\$session->redirect((string) \$down['redirect']);\n"
				. "\t\t}\n"
				. "\t\tif (!empty(\$down['retry'])) {\n"
				. "\t\t\theader('Retry-After: ' . (int) \$down['retry']);\n"
				. "\t\t}\n"
				. "\t\thttp_response_code((int) (\$down['status'] ?? 503));\n"
				. "\t\texit('Site is under maintenance.');\n"
				. "\t}\n"
				. "}"
			);

			return Command::SUCCESS;
		} catch (\Throwable $e) {
			return $this->failCommand($output, $json, 'MAINTENANCE_ERROR', 'Failed to enter maintenance mode: ' . $e->getMessage());
		}
	}
}
