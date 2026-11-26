<?php

namespace ForkPanelXc\Domain\Module;

abstract class Module implements ModuleInterface
{
    public function getId(): string
    {
        return strtolower(str_replace('Module', '', class_basename(static::class)));
    }

    public function getName(): string
    {
        return class_basename(static::class);
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getDescription(): string
    {
        return '';
    }

    public function getAuthor(): string
    {
        return 'Fork Panel XC';
    }

    public function getDependencies(): array
    {
        return [];
    }

    public function getPermissions(): array
    {
        return [];
    }

    public function getRoutes(): array
    {
        return [];
    }

    public function getMenuItems(): array
    {
        return [];
    }

    public function getSettingsSchema(): array
    {
        return [];
    }

    public function onInstall(): bool
    {
        return true;
    }

    public function onUpdate(string $fromVersion): bool
    {
        return true;
    }

    public function onUninstall(): bool
    {
        return true;
    }

    public function onEnable(): bool
    {
        return true;
    }

    public function onDisable(): bool
    {
        return true;
    }

    public function getCronJobs(): array
    {
        return [];
    }

    public function getCliCommands(): array
    {
        return [];
    }

    public function getDashboardWidgets(): array
    {
        return [];
    }

    public function getApiEndpoints(): array
    {
        return [];
    }
}