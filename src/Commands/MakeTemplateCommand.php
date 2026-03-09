<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\confirm;

final class MakeTemplateCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('make:template')
            ->setDescription('Create a new ProcessWire template and its associated file.')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the template')
            ->addOption('fields', null, InputOption::VALUE_REQUIRED, 'Comma-separated field names to attach (order respected)')
            ->addOption('interactive', 'i', InputOption::VALUE_NONE, 'Prompt to select fields to attach');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getArgument('name');
        $fieldsOpt = $input->getOption('fields') ? (string)$input->getOption('fields') : '';
        $wantInteractive = (bool)$input->getOption('interactive');

        if (\ProcessWire\wire('templates')->get($name)->id) {
            $io->error("Template '{$name}' already exists.");
            return Command::FAILURE;
        }

        $fieldsApi = \ProcessWire\wire('fields');
        $fgApi = \ProcessWire\wire('fieldgroups');
        $attach = [];
        if ($wantInteractive && $input->isInteractive()) {
            $all = [];
            foreach ($fieldsApi as $f) $all[] = $f->name;
            $sel = multiselect('Select fields to attach (Esc to skip):', $all, required: false);
            $attach = $sel ? array_values(array_unique(array_map('strval', $sel))) : [];
        } elseif ($fieldsOpt) {
            foreach (explode(',', $fieldsOpt) as $fn) {
                $fn = trim($fn);
                if ($fn !== '') $attach[] = $fn;
            }
            $attach = array_values(array_unique($attach));
        }

        // Create the template record in PW
        $t = new \ProcessWire\Template();
        $t->name = $name;

        // Create or reuse a fieldgroup for this template
        $fg = $fgApi->get($name);
        if (!$fg || !$fg->id) {
            $fg = new \ProcessWire\Fieldgroup();
            $fg->name = $name;
            $fg->save();
        }
        // Attach selected fields in order
        if ($attach) {
            foreach ($attach as $fn) {
                $f = $fieldsApi->get($fn);
                if ($f && $f->id && !$fg->has($f)) {
                    $fg->add($f);
                }
            }
            $fg->save();
        } else {
            // Default to 'default' fieldgroup when nothing selected
            $fg = $fgApi->get('default');
        }
        $t->fieldgroup = $fg;
        $t->save();

        $io->success("Template '{$name}' created in database.");

        // Create the template file
        $templatesPath = \ProcessWire\wire('config')->paths->templates;
        $filePath = "{$templatesPath}{$name}.php";

        if (!file_exists($filePath)) {
            $stubPath = __DIR__ . '/../../resources/stubs/template.stub';
            $content = file_exists($stubPath) ? file_get_contents($stubPath) : "<?php\n\ndeclare(strict_types=1);\n\n?>\n<h1><?= \$page->title ?></h1>\n";

            file_put_contents($filePath, $content);
            $io->info("Generated template file from stub: {$filePath}");
        } else {
            $io->warning("Template file '{$name}.php' already exists, skipping file creation.");
        }

        return Command::SUCCESS;
    }
}
