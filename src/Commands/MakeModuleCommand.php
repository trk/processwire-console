<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class MakeModuleCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('make:module')
            ->setDescription('Scaffold a new ProcessWire module.')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the module (e.g. HelloWorld)')
            ->addOption('type', 't', InputOption::VALUE_REQUIRED, 'Type of module (module, fieldtype, inputfield, process)', 'module')
            ->addOption('title', null, InputOption::VALUE_REQUIRED, 'Module title (defaults to name)')
            ->addOption('summary', null, InputOption::VALUE_REQUIRED, 'Short summary')
            ->addOption('author', null, InputOption::VALUE_REQUIRED, 'Author name', 'Wire-CLI Builder')
            ->addOption('mod-version', null, InputOption::VALUE_REQUIRED, 'Module version string', '0.0.1')
            ->addOption('autoload', null, InputOption::VALUE_NONE, 'Set autoload=true in stub (module type only)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getArgument('name');
        $type = strtolower($input->getOption('type'));
        $title = $input->getOption('title') ? (string)$input->getOption('title') : (string)$name;
        $summary = $input->getOption('summary') ? (string)$input->getOption('summary') : "A new {$type} module created via wire-cli.";
        $author = (string)$input->getOption('author');
        $version = (string)$input->getOption('mod-version');
        $autoloadFlag = (bool)$input->getOption('autoload');

        $modulesPath = \ProcessWire\wire('config')->paths->siteModules;
        $modulePath = $modulesPath . $name;

        if (is_dir($modulePath)) {
            $io->error("Module directory '{$name}' already exists.");
            return Command::FAILURE;
        }

        mkdir($modulePath, 0755, true);

        $moduleFile = "{$modulePath}/{$name}.module.php";

        $stubFile = match ($type) {
            'fieldtype' => 'fieldtype.stub',
            'inputfield' => 'inputfield.stub',
            'process' => 'process.stub',
            default => 'module.stub'
        };

        $stubPath = __DIR__ . '/../../resources/stubs/' . $stubFile;

        if (file_exists($stubPath)) {
            $content = file_get_contents($stubPath);
            $content = str_replace(
                ['{{className}}', '{{title}}', '{{summary}}', '{{author}}', '{{name}}'],
                [$name, $title, $summary, $author, strtolower($name)],
                $content
            );
            $content = str_replace("'version' => '0.0.1'", "'version' => '{$version}'", $content);
            if ($type === 'module') {
                if ($autoloadFlag) {
                    $content = str_replace("'autoload' => true", "'autoload' => true", $content);
                } else {
                    $content = str_replace("'autoload' => true", "'autoload' => false", $content);
                }
            }
        } else {
            $content = "<?php\n\nnamespace ProcessWire;\n\nclass {$name} extends WireData implements Module {\n    public static function getModuleInfo() {\n        return ['title' => '{$name}', 'version' => '0.0.1'];\n    }\n}";
        }

        file_put_contents($moduleFile, $content);
        $io->success("{$type} '{$name}' scaffolded from stub at: {$moduleFile}");

        $io->note("You can now install it via the ProcessWire admin or 'wire module:list'.");

        return Command::SUCCESS;
    }
}
