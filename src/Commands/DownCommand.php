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
			->addOption('status', null, InputOption::VALUE_REQUIRED, 'The HTTP status code (200-599, excluding 204/304) returned during maintenance mode', 503)
			->addOption('json', null, InputOption::VALUE_NONE, 'Output results as JSON');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$json = (bool) $input->getOption('json');
		$wire = \ProcessWire\wire();
		if (!$wire) {
			return $this->failCommand($output, $json, 'ENV_NOT_FOUND', 'ProcessWire environment not found.');
		}

		$rawStatus = trim((string) $input->getOption('status'));
		if ($rawStatus === '' || !ctype_digit($rawStatus)) {
			return $this->failCommand($output, $json, 'INVALID_STATUS', 'Option --status must be an integer between 200 and 599.');
		}
		$statusCode = (int) $rawStatus;
		if ($statusCode < 200 || $statusCode > 599 || in_array($statusCode, [204, 304], true)) {
			return $this->failCommand($output, $json, 'INVALID_STATUS', 'HTTP status code must be between 200 and 599 (excluding 204 and 304).');
		}

		$retryOpt = $input->getOption('retry');
		$retrySeconds = null;
		if ($retryOpt !== null && $retryOpt !== '') {
			$rawRetry = trim((string) $retryOpt);
			if (!ctype_digit($rawRetry) || (int) $rawRetry < 1) {
				return $this->failCommand($output, $json, 'INVALID_RETRY', 'Option --retry must be a positive integer.');
			}
			$retrySeconds = (int) $rawRetry;
		}

		$redirectOpt = $input->getOption('redirect');
		$redirectUri = null;
		if ($redirectOpt !== null && $redirectOpt !== '') {
			$candidate = trim((string) $redirectOpt);
			if (preg_match('/[\x00-\x1F\x7F]/', $candidate) === 1) {
				return $this->failCommand($output, $json, 'INVALID_REDIRECT', 'Option --redirect must not contain control characters.');
			}
			$isLocalPath = str_starts_with($candidate, '/') && !str_starts_with($candidate, '//') && !str_starts_with($candidate, '/\\');
			$isHttpUrl = preg_match('~^https?://[^\s/$.?#].\S*$~i', $candidate) === 1 && filter_var($candidate, FILTER_VALIDATE_URL) !== false;
			if (!$isLocalPath && !$isHttpUrl) {
				return $this->failCommand($output, $json, 'INVALID_REDIRECT', 'Option --redirect must be a root-relative path (/maintenance) or an http(s):// URL.');
			}
			$redirectUri = $candidate;
		}

		$secretOpt = $input->getOption('secret');
		$secretValue = null;
		if ($secretOpt !== null) {
			$trimmedSecret = trim((string) $secretOpt);
			if ($trimmedSecret === '' || preg_match('/[\x00-\x1F\x7F]/', $trimmedSecret) === 1) {
				return $this->failCommand($output, $json, 'INVALID_SECRET', 'Option --secret must be a non-empty string without control characters.');
			}
			$secretValue = $trimmedSecret;
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

			$payload = [
				'redirect' => $redirectUri,
				'retry' => $retrySeconds,
				'secret' => $secretValue,
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
				. "Add an early check in site/init.php (guarding !\$config->cli so 'wire up' keeps working):\n"
				. "\$downFile = \$config->paths->site . 'down.json';\n"
				. "\$reqPath = (string) (parse_url(\$_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');\n"
				. "if (!\$config->cli && is_file(\$downFile) && !is_link(\$downFile) && !str_starts_with(\$reqPath, \$config->urls->admin)) {\n"
				. "\t\$down = json_decode((string) file_get_contents(\$downFile), true) ?: [];\n"
				. "\t\$secret = (string) (\$down['secret'] ?? '');\n"
				. "\t\$querySecret = \$input->get->text('secret');\n"
				. "\t\$provided = \$querySecret !== '' ? \$querySecret : (string) \$session->get('maintenance_secret');\n"
				. "\tif (\$secret !== '' && hash_equals(\$secret, \$provided)) {\n"
				. "\t\t\$session->set('maintenance_secret', \$secret);\n"
				. "\t} else {\n"
				. "\t\t\$redirect = (string) (\$down['redirect'] ?? '');\n"
				. "\t\t\$redirectPath = (string) (parse_url(\$redirect, PHP_URL_PATH) ?: '');\n"
				. "\t\tif (\$redirect !== '' && \$reqPath !== \$redirectPath) {\n"
				. "\t\t\t\$session->redirect(\$redirect, false);\n"
				. "\t\t}\n"
				. "\t\tif (!empty(\$down['retry'])) {\n"
				. "\t\t\theader('Retry-After: ' . (int) \$down['retry']);\n"
				. "\t\t}\n"
				. "\t\thttp_response_code((int) (\$down['status'] ?? 503));\n"
				. "\t\texit('Service Unavailable');\n"
				. "\t}\n"
				. "}"
			);

			return Command::SUCCESS;
		} catch (\Throwable $e) {
			return $this->failCommand($output, $json, 'MAINTENANCE_ERROR', 'Failed to enter maintenance mode: ' . $e->getMessage());
		}
	}
}
