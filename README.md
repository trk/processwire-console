# ProcessWire Console (wire)

Laravel‑style CLI for ProcessWire. A single binary (vendor/bin/wire) built on Symfony Console and Laravel Prompts to manage pages, schema (fields/templates), modules, users/RBAC, logs, cache and backups in a safe, ergonomic way.

## Installation

```bash
composer require trk/processwire-console
```

Run the CLI:

```bash
php vendor/bin/wire list
```

On first run, the console locates the ProcessWire root and boots the core when possible. If database configuration is missing, only help and command lists are shown.

## Usage

```bash
php vendor/bin/wire list
php vendor/bin/wire help page:create
php vendor/bin/wire -q page:list
php vendor/bin/wire -v page:find "template=basic-page, limit=5"
php vendor/bin/wire -n field:update --name body --set "label=Body"
```

## Commands

Core commands shipped with this package. Use `help` to see options.

- General
  - list, help, tinker (ProcessWire REPL)
- Pages
  - page:list, page:find
  - page:create, page:update, page:move
  - page:publish, page:unpublish, page:trash, page:restore
- Fields
  - field:list, field:info, field:update, field:rename
  - field:attach, field:detach, field:delete
- Templates
  - template:list, template:info, template:update, template:rename
  - template:fields:reorder, template:delete
- Modules
  - module:list, module:install, module:uninstall, module:refresh
  - module:enable, module:disable, module:upgrade
- Users/RBAC
  - user:list, user:create, user:update, user:delete
  - role:list, role:create, role:grant, role:revoke
  - permission:list, permission:create, permission:delete
- Logs
  - logs, logs:tail, logs:clear
- Cache
  - cache:wire:clear, cache:clear
- Backup/DB
  - db:backup, db:restore
  - backup:list, backup:purge
- Make (scaffolding)
  - make:template, make:field, make:module

## Extending (Laravel‑like)

processwire-console loads external commands via Composer metadata.

Add to your package’s composer.json:

```json
{
  "extra": {
    "processwire-console": {
      "commands": [
        "Vendor\\Package\\Console\\Commands\\MyCommand"
      ]
    }
  },
  "autoload": {
    "psr-4": { "Vendor\\Package\\": "src/" }
  }
}
```

Ensure each class extends Symfony Console `Command`. The console reads Composer’s `vendor/composer/installed.json` and the root `composer.json` path repositories to register these classes automatically. Additionally, commands placed under `site/Commands/*.php` are auto‑loaded.

## Requirements

- PHP 8.3+
- processwire/processwire 3.x
- symfony/console 7.x
- laravel/prompts

## Language Policy

All code, documentation, examples, README content, issues and pull‑request descriptions must be in English only.

## Status & Contributions

This package is under active development. Suggestions, bug reports and pull requests are welcome. Please open an issue or submit a PR.

## License

MIT
