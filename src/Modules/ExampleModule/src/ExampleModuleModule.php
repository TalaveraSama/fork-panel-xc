<?php

namespace ForkPanelXc\Modules\ExampleModule;

use ForkPanelXc\Domain\Module\Module;

class ExampleModuleModule extends Module
{
    public function getName(): string
    {
        return 'Example Module';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getDescription(): string
    {
        return 'An example module demonstrating the Fork Panel XC module system';
    }

    public function getAuthor(): string
    {
        return 'Fork Panel XC Team';
    }

    public function getDependencies(): array
    {
        return [];
    }

    public function getPermissions(): array
    {
        return [
            'example_module.view' => 'View Example Module',
            'example_module.manage' => 'Manage Example Module',
        ];
    }

    public function getRoutes(): array
    {
        return [
            [
                'method' => 'GET',
                'path' => '/example-module/items',
                'handler' => [\ForkPanelXc\Modules\ExampleModule\Controllers\ExampleModuleController::class, 'index'],
                'middleware' => ['auth', 'permission:example_module.view'],
            ],
            [
                'method' => 'POST',
                'path' => '/example-module/items',
                'handler' => [\ForkPanelXc\Modules\ExampleModule\Controllers\ExampleModuleController::class, 'store'],
                'middleware' => ['auth', 'permission:example_module.manage'],
            ],
            [
                'method' => 'GET',
                'path' => '/example-module/items/{id}',
                'handler' => [\ForkPanelXc\Modules\ExampleModule\Controllers\ExampleModuleController::class, 'show'],
                'middleware' => ['auth', 'permission:example_module.view'],
            ],
            [
                'method' => 'PUT',
                'path' => '/example-module/items/{id}',
                'handler' => [\ForkPanelXc\Modules\ExampleModule\Controllers\ExampleModuleController::class, 'update'],
                'middleware' => ['auth', 'permission:example_module.manage'],
            ],
            [
                'method' => 'DELETE',
                'path' => '/example-module/items/{id}',
                'handler' => [\ForkPanelXc\Modules\ExampleModule\Controllers\ExampleModuleController::class, 'destroy'],
                'middleware' => ['auth', 'permission:example_module.manage'],
            ],
        ];
    }

    public function getMenuItems(): array
    {
        return [
            [
                'label' => 'Example Module',
                'icon' => 'puzzle',
                'route' => 'example-module.index',
                'permissions' => ['example_module.view'],
                'order' => 90,
            ],
        ];
    }

    public function getSettingsSchema(): array
    {
        return [
            'api_key' => [
                'type' => 'string',
                'label' => 'API Key',
                'description' => 'External API key for the example service',
                'required' => false,
                'sensitive' => true,
            ],
            'sync_interval' => [
                'type' => 'integer',
                'label' => 'Sync Interval (minutes)',
                'description' => 'How often to sync data from external service',
                'default' => 60,
                'min' => 5,
                'max' => 1440,
            ],
            'enable_notifications' => [
                'type' => 'boolean',
                'label' => 'Enable Notifications',
                'description' => 'Send notifications when sync completes',
                'default' => true,
            ],
        ];
    }

    public function getCronJobs(): array
    {
        return [
            [
                'name' => 'example_module_sync',
                'class' => \ForkPanelXc\Modules\ExampleModule\CronJobs\SyncCronJob::class,
                'schedule' => '*/15 * * * *', // Every 15 minutes
                'description' => 'Sync data from external service',
            ],
        ];
    }

    public function getCliCommands(): array
    {
        return [
            [
                'name' => 'example:sync',
                'class' => \ForkPanelXc\Modules\ExampleModule\Commands\SyncCommand::class,
                'description' => 'Manually trigger data sync',
            ],
            [
                'name' => 'example:cleanup',
                'class' => \ForkPanelXc\Modules\ExampleModule\Commands\CleanupCommand::class,
                'description' => 'Clean up old data',
            ],
        ];
    }

    public function getDashboardWidgets(): array
    {
        return [
            [
                'id' => 'example_module_stats',
                'name' => 'Example Module Stats',
                'component' => 'ExampleModuleStatsWidget',
                'size' => 'medium', // small, medium, large
                'permissions' => ['example_module.view'],
            ],
        ];
    }

    public function getApiEndpoints(): array
    {
        return [
            [
                'method' => 'GET',
                'path' => '/example-module/public/data',
                'handler' => [\ForkPanelXc\Modules\ExampleModule\Controllers\PublicController::class, 'getData'],
                'auth' => false,
            ],
        ];
    }

    public function onInstall(): bool
    {
        // Create database tables
        $this->createTables();
        return true;
    }

    public function onUpdate(string $fromVersion): bool
    {
        // Run migrations
        $this->runMigrations($fromVersion);
        return true;
    }

    public function onUninstall(): bool
    {
        // Clean up database tables
        $this->dropTables();
        return true;
    }

    public function onEnable(): bool
    {
        // Register cron jobs, commands, etc.
        return true;
    }

    public function onDisable(): bool
    {
        // Unregister cron jobs, commands, etc.
        return true;
    }

    private function createTables(): void
    {
        // This would use the database connection to create tables
        // Example: CREATE TABLE example_module_items (...)
    }

    private function runMigrations(string $fromVersion): void
    {
        // Run migration scripts based on version
    }

    private function dropTables(): void
    {
        // Drop module tables
    }
}