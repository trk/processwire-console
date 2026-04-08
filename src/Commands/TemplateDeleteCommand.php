<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\warning;
use function Laravel\Prompts\note;
use Totoglu\Console\Traits\InteractWithProcessWire;

final class TemplateDeleteCommand extends Command
{
    use InteractWithProcessWire;
    protected function configure(): void
    {
        $this
            ->setName('template:delete')
            ->setDescription('Delete a template.')
            ->addArgument('name', InputArgument::OPTIONAL, 'The name of the template to delete')
            ->addOption('json', null, InputOption::VALUE_NONE, 'JSON output')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip interactive confirmations');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $asJson = (bool)$input->getOption('json');
        $force = (bool)$input->getOption('force');
        
        if (!$name && !$asJson) {
            $name = $this->searchTemplate('Select the template to delete');
            if ($name === 'No matching templates found') return Command::SUCCESS;
        }

        if (!$name) {
            error("Provide template name.");
            return Command::FAILURE;
        }

        $template = \ProcessWire\wire('templates')->get($name);

        if (!$template || !$template->id) {
            error("Template '{$name}' not found.");
            return Command::FAILURE;
        }

        if ($template->flags & \ProcessWire\Template::flagSystem) {
            error("Template '{$name}' is a system template and cannot be deleted.");
            return Command::FAILURE;
        }

        if ($force || $asJson || confirm("Are you sure you want to delete template '{$name}'? This will delete all pages using it.", default: false)) {
            try {
                \ProcessWire\wire('templates')->delete($template);
                if ($asJson) {
                    $output->writeln(json_encode(['ok' => true, 'data' => ['name' => $name, 'deleted' => true]], JSON_UNESCAPED_SLASHES));
                } else {
                    info("Template '{$name}' deleted.");
                }
            } catch (\Exception $e) {
                error("Error deleting template: " . $e->getMessage());
                return Command::FAILURE;
            }
            return Command::SUCCESS;
        } else {
            note("Aborted.");
        }

        return Command::SUCCESS;
    }
}
