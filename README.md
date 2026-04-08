<p align="center">
  <strong>ProcessWire Console</strong><br>
  <code>vendor/bin/wire</code>
</p>

<p align="center">
  A professional CLI for <a href="https://processwire.com">ProcessWire CMS/CMF</a>.<br>
  57 production-ready commands · JSON output for AI agents · Composer-driven extensibility
</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php&logoColor=white" alt="PHP 8.3+">
  <img src="https://img.shields.io/badge/ProcessWire-3.x-2196F3?logo=data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCI+PC9zdmc+" alt="ProcessWire 3.x">
  <img src="https://img.shields.io/badge/Symfony_Console-7.x-000000?logo=symfony" alt="Symfony Console 7.x">
  <img src="https://img.shields.io/badge/License-MIT-green" alt="MIT License">
</p>

---

## Table of Contents

- [Why ProcessWire Console?](#why-processwire-console)
- [Installation](#installation)
- [Shell Alias (Recommended)](#shell-alias-recommended)
- [Quick Start](#quick-start)
- [Command Reference](#command-reference)
  - [Pages](#pages)
  - [Fields](#fields)
  - [Templates](#templates)
  - [Modules](#modules)
  - [Users & RBAC](#users--rbac)
  - [Cache](#cache)
  - [Logs](#logs)
  - [Database & Backup](#database--backup)
  - [Scaffolding](#scaffolding)
  - [Migrations](#migrations)
  - [Tinker (REPL)](#tinker-repl)
- [JSON Output (Machine-Readable)](#json-output-machine-readable)
- [Interactive Mode](#interactive-mode)
- [Extending the Console](#extending-the-console)
- [Security](#security)
- [Architecture](#architecture)
- [Requirements](#requirements)
- [License](#license)

---

## Why ProcessWire Console?

ProcessWire is a powerful CMF but lacks a first-party CLI. This package fills that gap with **57 production-ready commands** purpose-built for ProcessWire's architecture:

| Domain | Commands | Description |
|--------|----------|-------------|
| Content (Pages) | 8 commands | Create, update, publish, move, trash, restore |
| Schema (Fields) | 7 commands | List, info, create, update, rename, attach, detach |
| Schema (Templates) | 6 commands | List, info, create, update, rename, reorder fields |
| Modules | 5 commands | List, install, uninstall, refresh, upgrade |
| Users & RBAC | 11 commands | Users, roles, permissions management |
| Cache & Logs | 5 commands | Clear caches, tail/clear log files |
| Database | 4 commands | Backup, restore, list backups, purge old backups |
| Scaffolding | 2 commands | Generate modules and migrations |
| Migrations | 8 commands | Run, rollback, reset, refresh, fresh, status |
| Runtime | 2 commands | Interactive REPL, command listing |
| **Total** | **57 commands** | |

Every command supports `--json` for machine-readable output, `--dry-run` for safe previews, and `--force` for non-interactive scripting.

---

## Installation

```bash
composer require trk/processwire-console
```

A `wire` symlink is created at your project root automatically. If it isn't, run:

```bash
php vendor/bin/wire list
```

### Verify Installation

```bash
php vendor/bin/wire list
```

If the database is not configured, the CLI will still start and display command help. Full functionality requires a working ProcessWire installation with database access.

---

## Shell Alias (Recommended)

Typing `php vendor/bin/wire` every time is tedious. Set up a shell alias to use just `wire`:

### macOS / Linux (Bash/Zsh)

Add this line to your shell config file (`~/.zshrc`, `~/.bashrc`, or `~/.bash_profile`):

```bash
# Add ProcessWire Console alias
echo 'alias wire="php vendor/bin/wire"' >> ~/.zshrc && source ~/.zshrc
```

> For Bash users, replace `~/.zshrc` with `~/.bashrc`.

### Windows (PowerShell)

Run in PowerShell to add a persistent alias:

```powershell
# Create profile if it doesn't exist, then add alias
if (!(Test-Path $PROFILE)) { New-Item -Path $PROFILE -Force }
Add-Content $PROFILE 'function wire { php vendor/bin/wire @args }'
. $PROFILE
```

### Windows (CMD)

Create a `wire.bat` file in a directory that's in your PATH:

```cmd
@echo off
php vendor/bin/wire %*
```

### After Setup

Now you can use `wire` directly:

```bash
# Before
php vendor/bin/wire page:list --template=basic-page

# After ✨
wire page:list --template=basic-page
wire user:create -i
wire migrate:status
```

---

## Quick Start

```bash
# List all available commands
php vendor/bin/wire list

# Get detailed help for any command
php vendor/bin/wire help page:create

# Create a page
php vendor/bin/wire page:create --parent=/ --template=basic-page --title="Hello World"

# List pages as JSON
php vendor/bin/wire page:list --template=basic-page --json

# Create a user with roles
php vendor/bin/wire user:create

# Backup the database
php vendor/bin/wire db:backup

# Interactive REPL
php vendor/bin/wire tinker
```

---

## Command Reference

### Pages

Manage the ProcessWire page tree — create, update, publish, move, trash, and restore pages.

#### `page:list`

List pages with filtering, sorting, and pagination.

```bash
# List the 20 most recent pages
php vendor/bin/wire page:list

# Filter by template and parent
php vendor/bin/wire page:list --template=blog-post --parent=/blog/ --limit=50

# Sort by title ascending
php vendor/bin/wire page:list --sort=title --limit=10

# Include hidden and unpublished pages
php vendor/bin/wire page:list --include=all

# JSON output for scripting
php vendor/bin/wire page:list --template=product --json
```

**Options:**

| Option | Short | Description |
|--------|-------|-------------|
| `--template` | `-t` | Filter by template name |
| `--parent` | `-p` | Filter by parent path or ID |
| `--sort` | `-s` | Sort field (default: `-created`). Prefix with `-` for descending |
| `--limit` | `-l` | Number of results (default: `20`) |
| `--include` | `-i` | Include scope: `all`, `hidden`, `unpublished` (default: `all`) |
| `--json` | | Output as JSON |

---

#### `page:find`

Find pages using a raw ProcessWire selector string.

```bash
php vendor/bin/wire page:find "template=blog-post, title%=ProcessWire, limit=10"

# JSON output
php vendor/bin/wire page:find "parent=/products/, sort=-created, limit=5" --json
```

**Options:**

| Option | Description |
|--------|-------------|
| `selector` | Argument: ProcessWire selector string (required) |
| `--json` | Output as JSON |

---

#### `page:create`

Create a new page under a parent with a specific template.

```bash
# Basic creation
php vendor/bin/wire page:create --parent=/ --template=basic-page --title="About Us"

# Create as unpublished
php vendor/bin/wire page:create --parent=/blog/ --template=blog-post --title="Draft Post" --unpublished

# Fully interactive mode — prompts for template, parent, title, and all fields
php vendor/bin/wire page:create -i

# Preview without saving
php vendor/bin/wire page:create --parent=/ --template=basic-page --title="Test" --dry-run
```

**Options:**

| Option | Short | Description |
|--------|-------|-------------|
| `--parent` | `-p` | Parent page path or ID (required) |
| `--template` | `-t` | Template name (required) |
| `--title` | | Page title |
| `--name` | | URL-safe name (auto-generated from title if omitted) |
| `--unpublished` | | Create as unpublished |
| `--interactive` | `-i` | Prompt for all values including template field filling |
| `--dry-run` | | Preview changes without saving |
| `--json` | | Output as JSON |

---

#### `page:update`

Update page properties and field values.

```bash
# Update title
php vendor/bin/wire page:update --id=1042 --title="New Title"

# Set arbitrary fields with --set
php vendor/bin/wire page:update --id=1042 --set="summary=Updated summary" --set="body=New body content"

# Change page status
php vendor/bin/wire page:update --id=1042 --status=hidden

# Move and rename
php vendor/bin/wire page:update --id=1042 --parent=/another-parent/ --name=new-url-name

# Preview changes
php vendor/bin/wire page:update --id=1042 --title="Preview" --dry-run
```

**Options:**

| Option | Description |
|--------|-------------|
| `--id` | Page ID (required; or use `--path`) |
| `--path` | Page path (alternative to `--id`) |
| `--title` | New title |
| `--name` | New URL-safe name |
| `--parent` | New parent path or ID |
| `--template` | Change template |
| `--status` | Status: `published`, `unpublished`, `hidden`, `locked` |
| `--set` | Set field value as `key=value` (repeatable) |
| `--dry-run` | Preview without saving |
| `--json` | Output as JSON |

---

#### `page:publish` / `page:unpublish`

```bash
php vendor/bin/wire page:publish --id=1042
php vendor/bin/wire page:unpublish --id=1042

# Skip confirmation
php vendor/bin/wire page:publish --id=1042 --force
```

#### `page:trash` / `page:restore`

```bash
php vendor/bin/wire page:trash --id=1042
php vendor/bin/wire page:restore --id=1042

# Force without confirmation
php vendor/bin/wire page:trash --id=1042 --force
```

#### `page:move`

```bash
php vendor/bin/wire page:move --id=1042 --parent=/new-section/
php vendor/bin/wire page:move --id=1042 --parent=1001 --dry-run
```

---

### Fields

Full lifecycle management for ProcessWire fields.

#### `field:list`

```bash
# List all fields
php vendor/bin/wire field:list

# Filter by type
php vendor/bin/wire field:list --type=FieldtypeText

# Search by name or label
php vendor/bin/wire field:list --search=body

# JSON output
php vendor/bin/wire field:list --json
```

#### `field:info`

```bash
php vendor/bin/wire field:info --name=body
php vendor/bin/wire field:info --name=body --json
```

#### `field:update`

```bash
# Update label and description
php vendor/bin/wire field:update --name=body --set="label=Body Text" --set="description=Main content area"

# Change field settings
php vendor/bin/wire field:update --name=summary --set="maxlength=500" --set="required=1"
```

#### `field:rename`

```bash
php vendor/bin/wire field:rename --name=old_field_name --new-name=new_field_name
php vendor/bin/wire field:rename --name=body --new-name=content --dry-run
```

#### `field:attach` / `field:detach`

```bash
# Attach a field to a template
php vendor/bin/wire field:attach --template=blog-post --field=images

# Attach after a specific field
php vendor/bin/wire field:attach --template=blog-post --field=tags --after=body

# Detach a field from a template
php vendor/bin/wire field:detach --template=blog-post --field=sidebar
```

#### `field:delete`

```bash
php vendor/bin/wire field:delete --name=unused_field
php vendor/bin/wire field:delete --name=unused_field --force
```

---

### Templates

#### `template:list`

```bash
php vendor/bin/wire template:list
php vendor/bin/wire template:list --search=blog --json
```

#### `template:info`

```bash
php vendor/bin/wire template:info --name=basic-page
php vendor/bin/wire template:info --name=basic-page --json
```

#### `template:update`

```bash
php vendor/bin/wire template:update --name=blog-post --set="label=Blog Article"
php vendor/bin/wire template:update --name=blog-post --set="noChildren=1" --set="noParents=-1"
```

#### `template:rename`

```bash
php vendor/bin/wire template:rename --name=old-template --new-name=new-template
```

#### `template:fields:reorder`

```bash
# Reorder fields within a template
php vendor/bin/wire template:fields:reorder --template=blog-post --fields="title,body,images,tags,summary"
```

#### `template:delete`

```bash
php vendor/bin/wire template:delete --name=unused-template
php vendor/bin/wire template:delete --name=unused-template --force
```

---

### Modules

A complete module lifecycle manager — discover, install, enable, disable, upgrade.

#### `module:list`

```bash
# List all installed modules
php vendor/bin/wire module:list

# Core modules only
php vendor/bin/wire module:list --core

# Site (third-party) modules only
php vendor/bin/wire module:list --site

# Search
php vendor/bin/wire module:list --search=Image

# JSON output
php vendor/bin/wire module:list --site --json
```

#### `module:install` / `module:uninstall`

```bash
php vendor/bin/wire module:install --name=ModuleName
php vendor/bin/wire module:uninstall --name=ModuleName --force
```



#### `module:refresh`

```bash
# Refresh all module caches
php vendor/bin/wire module:refresh

# Refresh a specific module
php vendor/bin/wire module:refresh --name=ModuleName
```

#### `module:upgrade`

```bash
php vendor/bin/wire module:upgrade --name=ModuleName
```

---

### Users & RBAC

Complete role-based access control management from the command line.

#### Users

```bash
# List all users
php vendor/bin/wire user:list
php vendor/bin/wire user:list --role=editor --json

# Create a user (interactive prompts for password, email, roles)
php vendor/bin/wire user:create

# Create a user non-interactively
php vendor/bin/wire user:create --name=john --email=john@example.com --password=SecretPass --role=editor --force

# Update a user
php vendor/bin/wire user:update --name=john --email=new@example.com
php vendor/bin/wire user:update --name=john --add-role=admin --remove-role=guest

# Delete a user
php vendor/bin/wire user:delete --name=john
php vendor/bin/wire user:delete --id=1045 --force
```

#### Roles

```bash
# List roles
php vendor/bin/wire role:list
php vendor/bin/wire role:list --json

# Create a role
php vendor/bin/wire role:create --name=editor --title="Content Editor"

# Grant a permission to a role
php vendor/bin/wire role:grant --role=editor --permission=page-edit

# Revoke a permission from a role
php vendor/bin/wire role:revoke --role=editor --permission=page-delete
```

#### Permissions

```bash
# List permissions
php vendor/bin/wire permission:list

# Create a custom permission
php vendor/bin/wire permission:create --name=my-custom-permission

# Delete a custom permission
php vendor/bin/wire permission:delete --name=my-custom-permission
```

---

### Cache

#### `cache:clear`

Clears compiled files, session caches, and file caches.

```bash
php vendor/bin/wire cache:clear
php vendor/bin/wire cache:clear --json
```

#### `cache:wire:clear`

Clear WireCache entries by exact key or SQL LIKE pattern.

```bash
# Clear a specific cache key
php vendor/bin/wire cache:wire:clear --key=MyModule.someData

# Clear all cache entries matching a pattern
php vendor/bin/wire cache:wire:clear --pattern="Template%"
php vendor/bin/wire cache:wire:clear --pattern="MyModule.%" --json
```

---

### Logs

#### `logs`

List available log files.

```bash
php vendor/bin/wire logs
php vendor/bin/wire logs --json
```

#### `logs:tail`

Read the last N lines of a log file.

```bash
# Read errors.txt (last 20 lines by default)
php vendor/bin/wire logs:tail --file=errors

# Read more lines
php vendor/bin/wire logs:tail --file=exceptions --lines=100

# JSON output
php vendor/bin/wire logs:tail --file=errors --json
```

#### `logs:clear`

Clear a specific log file.

```bash
php vendor/bin/wire logs:clear --file=errors
php vendor/bin/wire logs:clear --file=exceptions --force
```

---

### Database & Backup

#### `db:backup`

Create a database backup using ProcessWire's native `WireDatabaseBackup`.

```bash
php vendor/bin/wire db:backup
php vendor/bin/wire db:backup --json
```

#### `db:restore`

Restore the database from a backup file.

```bash
php vendor/bin/wire db:restore --file=backup-2026-04-08.sql
php vendor/bin/wire db:restore --file=backup-2026-04-08.sql --force
```

> **Warning:** This operation replaces your entire database. Always back up before restoring.

#### `backup:list`

```bash
php vendor/bin/wire backup:list
php vendor/bin/wire backup:list --json
```

#### `backup:purge`

Remove old backup files, keeping only the most recent N backups.

```bash
# Keep the 5 most recent backups
php vendor/bin/wire backup:purge --keep=5
php vendor/bin/wire backup:purge --keep=3 --force
```

---

### Scaffolding



#### `make:module`

Scaffold a new ProcessWire module from a professional stub.

```bash
# Standard module
php vendor/bin/wire make:module HelloWorld

# Fieldtype module
php vendor/bin/wire make:module FieldtypeColor --type=fieldtype

# Inputfield module
php vendor/bin/wire make:module InputfieldColor --type=inputfield

# Process (admin page) module
php vendor/bin/wire make:module ProcessDashboard --type=process

# With custom metadata
php vendor/bin/wire make:module MyModule \
  --type=module \
  --title="My Custom Module" \
  --summary="Does amazing things" \
  --author="Developer Name" \
  --mod-version=1.0.0 \
  --autoload
```

**Module types and stubs:**

| Type | Base Class | Use Case |
|------|-----------|----------|
| `module` | `WireData` | General-purpose module with init/ready hooks |
| `fieldtype` | `Fieldtype` | Custom database field storage |
| `inputfield` | `Inputfield` | Custom form input widget |
| `process` | `Process` | Admin page with URL routing |

#### `make:migration`

Scaffold a new timestamped migration file. See [Migrations](#migrations) for full documentation.

```bash
php vendor/bin/wire make:migration create_blog_section
php vendor/bin/wire make:migration add_body_field --type=create-field --field=body
```

---

### Migrations

A complete migration system for ProcessWire — schema versioning with up/down methods, batch tracking, rollback support, and 7 typed stubs for common PW operations.

Migrations live in `site/migrations/` and are tracked in the `wire_migrations` database table (auto-created on first run).

#### `make:migration`

Generate a new timestamped migration file from a stub.

```bash
# Blank migration
php vendor/bin/wire make:migration create_blog_section

# Create a field
php vendor/bin/wire make:migration add_subtitle_field --type=create-field --field=subtitle --fieldtype=FieldtypeText

# Create a template
php vendor/bin/wire make:migration create_blog_template --type=create-template --template=blog-post --label="Blog Post"

# Attach field to template
php vendor/bin/wire make:migration attach_images_to_blog --type=attach-field --template=blog-post --field=images

# Create a page
php vendor/bin/wire make:migration create_about_page --type=create-page --template=basic-page --parent=/ --label="About Us"

# Create a role with permissions
php vendor/bin/wire make:migration create_editor_role --type=create-role --label="Content Editor"

# Install a module
php vendor/bin/wire make:migration install_seo_module --type=install-module --module=SeoMaestro
```

**Stub types:**

| `--type` | Generates | up() | down() |
|----------|-----------|------|--------|
| `blank` | Empty skeleton (default) | — | — |
| `create-field` | Field creation | `new Field()` + save | delete field |
| `create-template` | Template + fieldgroup + file | Full scaffold | delete template + file |
| `attach-field` | Field-to-template attachment | fieldgroup add | fieldgroup remove |
| `create-page` | Page creation | `new Page()` + save | delete page |
| `create-role` | Role + permission grants | roles add + grant | delete role |
| `install-module` | Module install | `modules->install()` | uninstall |

#### Dependency Order (Critical)

ProcessWire objects have strict dependency chains. **Migrations must respect this order:**

```
CREATE order:  Field → Fieldgroup → Template → Page
DELETE order:  Page → Template → Fieldgroup → Field
```

For a typical blog setup, the **correct migration sequence** is:

```bash
# 1. Create fields first
php vendor/bin/wire make:migration create_body_field     --type=create-field --field=body
php vendor/bin/wire make:migration create_summary_field  --type=create-field --field=summary

# 2. Create template (depends on fields existing)
php vendor/bin/wire make:migration create_blog_template  --type=create-template --template=blog-post

# 3. Attach fields to template
php vendor/bin/wire make:migration attach_body_to_blog   --type=attach-field --template=blog-post --field=body
php vendor/bin/wire make:migration attach_summary_to_blog --type=attach-field --template=blog-post --field=summary

# 4. Create pages last (depends on template existing)
php vendor/bin/wire make:migration create_blog_page      --type=create-page --template=blog-post --parent=/
```

**On rollback**, these run in **reverse order** (last-in-first-out) — pages deleted before templates, fields detached before being deleted.

#### Safe-by-Default Guards

All stubs include **precondition guards** in `down()` that throw clear errors instead of cascading destructive operations:

| Stub | Guard | Error |
|------|-------|-------|
| `create-field` | Field attached to template(s)? | `"Cannot delete — detach first"` |
| `create-template` | Pages using this template? | `"Cannot delete — remove pages first"` |
| `attach-field` | Pages have data in this field? | `"Cannot detach — data would be lost"` |
| `create-page` | Page has children? | `"Cannot delete — remove children first"` |
| `create-role` | Users assigned this role? | `"Cannot delete — remove from users first"` |
| `install-module` | Other modules depend on it? | `"Cannot uninstall — dependencies exist"` |

> **Design philosophy:** Stubs never auto-delete user content or cascade-remove dependencies. They fail loudly so you can write the correct cleanup migration yourself.

Each migration file returns an anonymous class with `up()` and `down()` methods:

```php
<?php
declare(strict_types=1);
namespace ProcessWire;

return new class {
    public function up(): void
    {
        $field = new Field();
        $field->type = wire('modules')->get('FieldtypeText');
        $field->name = 'subtitle';
        $field->label = 'Subtitle';
        wire('fields')->save($field);
    }

    public function down(): void
    {
        $field = wire('fields')->get('subtitle');
        if (!$field || !$field->id) {
            return;
        }

        // Guard: field must not be attached to any template
        $fieldgroups = $field->getFieldgroups();
        if ($fieldgroups->count() > 0) {
            $names = $fieldgroups->implode(', ', 'name');
            throw new WireException(
                "Cannot delete field 'subtitle' — attached to: {$names}. Detach first."
            );
        }

        wire('fields')->delete($field);
    }
};
```

---

#### `migrate`

Run all pending migrations.

```bash
# Apply all pending
php vendor/bin/wire migrate

# Apply one at a time
php vendor/bin/wire migrate --step=1

# Preview without applying
php vendor/bin/wire migrate --dry-run

# Non-interactive
php vendor/bin/wire migrate --force

# JSON output
php vendor/bin/wire migrate --json
```

#### `migrate:rollback`

Rollback the last batch of migrations (calls `down()` in reverse order).

```bash
# Rollback last batch
php vendor/bin/wire migrate:rollback

# Rollback last 3 individual migrations
php vendor/bin/wire migrate:rollback --step=3

# Preview
php vendor/bin/wire migrate:rollback --dry-run
```

#### `migrate:reset`

Rollback ALL applied migrations.

```bash
php vendor/bin/wire migrate:reset
php vendor/bin/wire migrate:reset --force
```

#### `migrate:refresh`

Reset + re-run all migrations (shortcut for `reset` then `migrate`).

```bash
php vendor/bin/wire migrate:refresh
php vendor/bin/wire migrate:refresh --force --json
```

#### `migrate:fresh`

Drop the tracking table entirely and re-run all migrations from scratch.

```bash
php vendor/bin/wire migrate:fresh
php vendor/bin/wire migrate:fresh --force
```

> **Warning:** `migrate:fresh` does not call `down()` — it drops the tracking table and re-applies all migrations. Use `migrate:refresh` if you need clean rollbacks.

#### `migrate:status`

Show the status of all migration files in a formatted table.

```bash
php vendor/bin/wire migrate:status
php vendor/bin/wire migrate:status --json
```

**Output example:**

```
+---------------------------------------------+---------+-------+
| Migration                                   | Status  | Batch |
+---------------------------------------------+---------+-------+
| 20260408120000_create_blog_template.php      | Applied |     1 |
| 20260408120100_add_featured_image_field.php  | Applied |     1 |
| 20260408120200_create_editor_role.php        | Pending |     - |
+---------------------------------------------+---------+-------+
 ! [NOTE] 2 applied, 1 pending.
```

#### `migrate:install`

Explicitly create the `wire_migrations` tracking table (auto-created by `migrate` if missing).

```bash
php vendor/bin/wire migrate:install
```

---

### Tinker (REPL)

An interactive ProcessWire PHP REPL for quick testing and debugging.

```bash
php vendor/bin/wire tinker
```

```
ProcessWire Tinker — type "exit" to quit.
>>> $pages->count("template=basic-page")
12
>>> $user->name
admin
>>> $config->urls->templates
/site/templates/
```

> **Security:** Set `PW_CLI_DISABLE_TINKER=1` in production environments to disable this command.

---

## JSON Output (Machine-Readable)

Every command supports the `--json` flag for machine-readable output. This makes ProcessWire Console first-class for CI/CD pipelines, AI agent integrations, and automation scripts.

**Response format:**

```json
{
  "ok": true,
  "data": {
    "items": [],
    "total": 0
  }
}
```

**Error format:**

```json
{
  "ok": false,
  "error": {
    "code": "NOT_FOUND",
    "message": "Page not found: /nonexistent"
  }
}
```

**Examples:**

```bash
# Pipe page data to jq
php vendor/bin/wire page:list --template=blog-post --json | jq '.data.items[].title'

# Count modules
php vendor/bin/wire module:list --site --json | jq '.data.total'

# Use in shell scripts
BACKUP=$(php vendor/bin/wire db:backup --json | jq -r '.data.file')
echo "Backup saved to: $BACKUP"
```

---

## Interactive Mode

Commands that create or modify data support interactive prompts powered by [Laravel Prompts](https://github.com/laravel/prompts):

- **`page:create -i`** — Select template from a list, enter parent path, fill template fields interactively
- **`user:create`** — Prompts for username, email, password (with confirmation), and role selection


Interactive prompts are automatically disabled when:
- The `--json` flag is present
- The `--force` flag is present
- The terminal is non-interactive (piped input)

---

## Extending the Console

### Method 1: Site Commands

Place command files in `site/Commands/`. They are auto-loaded on every CLI invocation.

```php
<?php
// site/Commands/SyncProductsCommand.php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SyncProductsCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('products:sync')
            ->setDescription('Synchronize products from external API.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        // Your logic here — $pages, $modules etc. are available via wire()
        $io->success('Products synchronized.');
        return Command::SUCCESS;
    }
}
```

### Method 2: Composer Package Discovery

Register commands in your package's `composer.json`:

```json
{
  "name": "vendor/my-pw-package",
  "extra": {
    "processwire-console": {
      "commands": [
        "Vendor\\MyPackage\\Commands\\ImportCommand",
        "Vendor\\MyPackage\\Commands\\ExportCommand"
      ]
    }
  },
  "autoload": {
    "psr-4": {
      "Vendor\\MyPackage\\": "src/"
    }
  }
}
```

The console engine reads `vendor/composer/installed.json` **and** root `composer.json` path repositories to discover commands automatically.

---

## Security

### Path Traversal Protection

Log commands (`logs:tail`, `logs:clear`) sanitize the `--file` option with `basename()` to prevent directory traversal attacks.

### Tinker Guard

The `tinker` command executes arbitrary PHP via `eval()`. To disable it in production:

```bash
export PW_CLI_DISABLE_TINKER=1
```

### Confirmation Prompts

All destructive commands (`*:delete`, `*:trash`, `db:restore`, `backup:purge`, `migrate:reset`, `migrate:fresh`) require explicit confirmation. Use `--force` to bypass for scripting.

### Migration Safety

Migration stubs enforce **safe-by-default guards** in `down()` — they never cascade-delete user content. If a field is in use, a template has pages, or a role is assigned to users, the rollback will throw a descriptive `WireException` instead of silently destroying data. See [Safe-by-Default Guards](#safe-by-default-guards) for details.

### Dry-Run Mode

Preview any mutation with `--dry-run`:

```bash
php vendor/bin/wire page:update --id=1042 --title="Test" --dry-run
php vendor/bin/wire template:delete --name=old-template --dry-run
php vendor/bin/wire db:restore --file=backup.sql --dry-run
```

---

## Architecture

```
processwire-console/
├── bin/
│   └── wire                    # CLI entry point (bootstrap + command registration)
├── src/
│   ├── Commands/               # 57 command classes
│   │   ├── Page*.php           # 8 page management commands
│   │   ├── Field*.php          # 7 field management commands
│   │   ├── Template*.php       # 6 template management commands
│   │   ├── Module*.php         # 7 module lifecycle commands
│   │   ├── User*.php           # 4 user commands
│   │   ├── Role*.php           # 4 role commands
│   │   ├── Permission*.php     # 3 permission commands
│   │   ├── Migrate*.php        # 8 migration commands
│   │   ├── Cache*.php          # 2 cache commands
│   │   ├── Logs*.php           # 3 log commands
│   │   ├── Db*.php             # 2 database commands
│   │   ├── Backup*.php         # 2 backup commands
│   │   ├── Make*.php           # 4 scaffolding commands (incl. make:migration)
│   │   └── TinkerCommand.php   # Interactive REPL
│   └── Migration/              # Migration engine
│       ├── MigrationRepository.php  # Database tracking (wire_migrations table)
│       └── Migrator.php             # Core engine (discovery, up/down, batches)
├── resources/
│   └── stubs/                  # Scaffolding templates
│       ├── module.stub
│       ├── fieldtype.stub
│       ├── inputfield.stub
│       ├── process.stub
│       ├── template.stub
│       ├── migration.stub                 # Blank migration
│       ├── migration.create-field.stub    # Field creation
│       ├── migration.create-template.stub # Template + fieldgroup
│       ├── migration.attach-field.stub    # Field-to-template
│       ├── migration.create-page.stub     # Page creation
│       ├── migration.create-role.stub     # Role + permissions
│       └── migration.install-module.stub  # Module install
└── composer.json
```

### Bootstrap Flow

1. Locate Composer autoloader (supports standard and symlinked setups)
2. Walk upward to find `wire/core/ProcessWire.php`
3. Boot ProcessWire with database config (graceful fallback if unavailable)
4. Register 57 built-in commands (including 8 migration commands)
5. Auto-load `site/Commands/*.php`
6. Discover commands from Composer packages (`extra.processwire-console.commands`)
7. Run the Symfony Console application

### Design Principles

- **Null-safe:** All ProcessWire API `->get()` calls are guarded against `null` returns
- **Strict typing:** `declare(strict_types=1)` in every file
- **Consistent I/O:** All commands use `SymfonyStyle` for formatted output
- **Dual output:** Human-readable tables + machine-readable JSON on every command
- **Non-destructive defaults:** Confirmations on mutations, `--dry-run` for previews

---

## Requirements

| Dependency | Version |
|-----------|---------|
| PHP | ≥ 8.3 |
| ProcessWire | 3.x |
| `symfony/console` | ^7.0 |
| `laravel/prompts` | ^0.3.13 |

---

## License

MIT — see [LICENSE](LICENSE) for details.
