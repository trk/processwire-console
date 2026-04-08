<?php

declare(strict_types=1);

namespace Totoglu\Console\Traits;

use function Laravel\Prompts\search;
use function Laravel\Prompts\multisearch;
use function Laravel\Prompts\multiselect;

trait InteractWithProcessWire
{
    /**
     * Search for a ProcessWire Field interactively.
     *
     * @param string $label
     * @return string Field name
     */
    protected function searchField(string $label = 'Search for a field'): string
    {
        return search(
            label: $label,
            options: function (string $value) {
                $options = [];
                $fields = \ProcessWire\wire('fields')->find("name%=$value, limit=15");
                foreach ($fields as $field) {
                    $options[] = $field->name;
                }
                return empty($options) ? ['No matching fields found'] : $options;
            },
            placeholder: 'Type field name...'
        );
    }

    /**
     * Search for a ProcessWire Template interactively.
     *
     * @param string $label
     * @return string Template name
     */
    protected function searchTemplate(string $label = 'Search for a template'): string
    {
        return search(
            label: $label,
            options: function (string $value) {
                $options = [];
                $templates = \ProcessWire\wire('templates')->find("name%=$value, limit=15");
                foreach ($templates as $template) {
                    $options[] = $template->name;
                }
                return empty($options) ? ['No matching templates found'] : $options;
            },
            placeholder: 'Type template name...'
        );
    }

    /**
     * Search for a ProcessWire Role interactively.
     *
     * @param string $label
     * @return string Role name
     */
    protected function searchRole(string $label = 'Search for a role'): string
    {
        return search(
            label: $label,
            options: function (string $value) {
                $options = [];
                $roles = \ProcessWire\wire('roles')->find("name%=$value, limit=15");
                foreach ($roles as $role) {
                    $options[] = $role->name;
                }
                return empty($options) ? ['No matching roles found'] : $options;
            },
            placeholder: 'Type role name...'
        );
    }

    /**
     * Search for a ProcessWire Permission interactively.
     *
     * @param string $label
     * @return string Permission name
     */
    protected function searchPermission(string $label = 'Search for a permission'): string
    {
        return search(
            label: $label,
            options: function (string $value) {
                $options = [];
                $permissions = \ProcessWire\wire('permissions')->find("name%=$value, limit=15");
                foreach ($permissions as $permission) {
                    $options[] = $permission->name;
                }
                return empty($options) ? ['No matching permissions found'] : $options;
            },
            placeholder: 'Type permission name...'
        );
    }

    /**
     * Search for a ProcessWire User interactively.
     *
     * @param string $label
     * @return string User name
     */
    protected function searchUser(string $label = 'Search for a user'): string
    {
        return search(
            label: $label,
            options: function (string $value) {
                $options = [];
                // Search by name or email
                // Be sure value is sanitized for selector
                $sanitizer = \ProcessWire\wire('sanitizer');
                $q = $sanitizer->selectorValue($value);
                $users = \ProcessWire\wire('users')->find("name|email%=$q, limit=15");
                foreach ($users as $user) {
                    $options[] = $user->name;
                }
                return empty($options) ? ['No matching users found'] : $options;
            },
            placeholder: 'Type user name or email...'
        );
    }

    /**
     * Search for a ProcessWire Page interactively.
     *
     * @param string $label
     * @return string|int Page path or ID
     */
    protected function searchPage(string $label = 'Search for a page (by title or path)'): string|int
    {
        return search(
            label: $label,
            options: function (string $value) {
                $options = [];
                $sanitizer = \ProcessWire\wire('sanitizer');
                $q = $sanitizer->selectorValue($value);
                $pages = \ProcessWire\wire('pages')->find("title|path|name%=$q, limit=15");
                foreach ($pages as $page) {
                    $options[$page->id] = "{$page->title} ({$page->path})";
                }
                return empty($options) ? ['' => 'No matching pages found'] : $options;
            },
            placeholder: 'Type page title or path...'
        );
    }

    /**
     * Search for an installed ProcessWire Module interactively.
     *
     * @param string $label
     * @return string Module class name
     */
    protected function searchInstalledModule(string $label = 'Search for an installed module'): string
    {
        return search(
            label: $label,
            options: function (string $value) {
                $options = [];
                $modules = \ProcessWire\wire('modules');
                foreach ($modules->getAll() as $name => $module) {
                    if (empty($value) || stripos($name, $value) !== false) {
                        $options[] = $name;
                    }
                }
                
                // Limit to 15
                $options = array_slice($options, 0, 15);
                
                return empty($options) ? ['No matching modules found'] : $options;
            },
            placeholder: 'Type module name...'
        );
    }

    /**
     * Search for an installable ProcessWire Module interactively.
     *
     * @param string $label
     * @return string Module class name
     */
    protected function searchInstallableModule(string $label = 'Search for a module to install'): string
    {
        return search(
            label: $label,
            options: function (string $value) {
                $options = [];
                $modules = \ProcessWire\wire('modules');
                
                // ProcessWire often caches this, but we'll get whatever's available
                $installable = $modules->getInstallable();
                
                foreach ($installable as $name) {
                    if (empty($value) || stripos($name, $value) !== false) {
                        $options[] = $name;
                    }
                }
                
                $options = array_slice($options, 0, 15);
                
                return empty($options) ? ['No matching installable modules found'] : $options;
            },
            placeholder: 'Type module name...'
        );
    }

    /**
     * Search for a ProcessWire Log interactively.
     *
     * @param string $label
     * @return string Log name
     */
    protected function searchLog(string $label = 'Search for a log'): string
    {
        return search(
            label: $label,
            options: function (string $value) {
                $options = [];
                $logs = \ProcessWire\wire('log')->getLogs();
                foreach ($logs as $name => $logInfo) {
                    if (empty($value) || stripos($name, $value) !== false) {
                        $options[] = $name;
                    }
                }
                
                $options = array_slice($options, 0, 15);
                
                return empty($options) ? ['No matching logs found'] : $options;
            },
            placeholder: 'Type log name...'
        );
    }

    /**
     * Select multiple ProcessWire Logs interactively.
     *
     * @param string $label
     * @return array Selected log names
     */
    protected function multiselectLogs(string $label = 'Select logs to clear'): array
    {
        $logs = \ProcessWire\wire('log')->getLogs();
        $options = array_keys($logs);

        if (empty($options)) {
            return [];
        }

        return multiselect(
            label: $label,
            options: $options,
            hint: 'Use Space to select, Enter to confirm.'
        );
    }
}
