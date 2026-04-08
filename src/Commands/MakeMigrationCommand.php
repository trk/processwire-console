<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Totoglu\Console\Migration\Migrator;
use Totoglu\Console\Migration\MigrationRepository;

final class MakeMigrationCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('make:migration')
            ->setDescription('Create a new migration file.')
            ->addArgument('name', InputArgument::REQUIRED, 'Migration name (e.g. create_blog_template)')
            ->addOption('type', 't', InputOption::VALUE_REQUIRED, 'Stub type: blank, create-field, create-template, attach-field, create-page, create-role, install-module', 'blank')
            ->addOption('field', null, InputOption::VALUE_REQUIRED, 'Field name (for create-field and attach-field types)')
            ->addOption('fieldtype', null, InputOption::VALUE_REQUIRED, 'Fieldtype module name (for create-field)', 'FieldtypeText')
            ->addOption('template', null, InputOption::VALUE_REQUIRED, 'Template name (for create-template and attach-field types)')
            ->addOption('label', null, InputOption::VALUE_REQUIRED, 'Label for the created item')
            ->addOption('parent', null, InputOption::VALUE_REQUIRED, 'Parent page path (for create-page)', '/')
            ->addOption('module', null, InputOption::VALUE_REQUIRED, 'Module class name (for install-module)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $name = (string)$input->getArgument('name');
        $type = (string)$input->getOption('type');

        $migrator = new Migrator(new MigrationRepository());
        $migrator->ensureMigrationsDirectory();

        // Build filename with timestamp prefix
        $timestamp = date('YmdHis');
        $safeName = preg_replace('/[^a-z0-9_]/', '_', strtolower($name));
        $fileName = "{$timestamp}_{$safeName}.php";
        $filePath = $migrator->getMigrationsPath() . $fileName;

        if (file_exists($filePath)) {
            $io->error("Migration file already exists: {$fileName}");
            return Command::FAILURE;
        }

        // Resolve stub
        $stubMap = [
            'blank' => 'migration.stub',
            'create-field' => 'migration.create-field.stub',
            'create-template' => 'migration.create-template.stub',
            'attach-field' => 'migration.attach-field.stub',
            'create-page' => 'migration.create-page.stub',
            'create-role' => 'migration.create-role.stub',
            'install-module' => 'migration.install-module.stub',
        ];

        $stubFile = $stubMap[$type] ?? null;
        if ($stubFile === null) {
            $io->error("Unknown migration type: {$type}. Valid types: " . implode(', ', array_keys($stubMap)));
            return Command::FAILURE;
        }

        $stubPath = __DIR__ . '/../../resources/stubs/' . $stubFile;
        if (!file_exists($stubPath)) {
            $io->error("Stub file not found: {$stubFile}");
            return Command::FAILURE;
        }

        $content = (string)file_get_contents($stubPath);

        // Replace placeholders based on type
        $replacements = $this->buildReplacements($input, $name, $type);
        foreach ($replacements as $placeholder => $value) {
            $content = str_replace("{{{$placeholder}}}", $value, $content);
        }

        file_put_contents($filePath, $content);

        $io->success("Created migration: {$fileName}");
        $io->note("Path: {$filePath}");

        return Command::SUCCESS;
    }

    /**
     * Build placeholder replacements based on migration type.
     *
     * @return array<string, string>
     */
    private function buildReplacements(InputInterface $input, string $name, string $type): array
    {
        $replacements = [];

        switch ($type) {
            case 'create-field':
                $replacements['name'] = (string)($input->getOption('field') ?: $name);
                $replacements['fieldtype'] = (string)$input->getOption('fieldtype');
                $replacements['label'] = (string)($input->getOption('label') ?: ucfirst(str_replace('_', ' ', $replacements['name'])));
                break;

            case 'create-template':
                $replacements['name'] = (string)($input->getOption('template') ?: $name);
                $replacements['label'] = (string)($input->getOption('label') ?: ucfirst(str_replace(['_', '-'], ' ', $replacements['name'])));
                break;

            case 'attach-field':
                $replacements['template'] = (string)($input->getOption('template') ?: '');
                $replacements['field'] = (string)($input->getOption('field') ?: '');
                if (!$replacements['template'] || !$replacements['field']) {
                    // Will be filled by user in the stub
                    $replacements['template'] = $replacements['template'] ?: 'TEMPLATE_NAME';
                    $replacements['field'] = $replacements['field'] ?: 'FIELD_NAME';
                }
                break;

            case 'create-page':
                $replacements['parent'] = (string)$input->getOption('parent');
                $replacements['template'] = (string)($input->getOption('template') ?: 'basic-page');
                $replacements['name'] = preg_replace('/[^a-z0-9\-]/', '-', strtolower($name));
                $replacements['title'] = (string)($input->getOption('label') ?: ucfirst(str_replace(['_', '-'], ' ', $name)));
                break;

            case 'create-role':
                $replacements['name'] = (string)($input->getOption('field') ?: preg_replace('/[^a-z0-9\-]/', '-', strtolower($name)));
                $replacements['title'] = (string)($input->getOption('label') ?: ucfirst(str_replace(['_', '-'], ' ', $name)));
                break;

            case 'install-module':
                $replacements['name'] = (string)($input->getOption('module') ?: $name);
                break;
        }

        return $replacements;
    }
}
