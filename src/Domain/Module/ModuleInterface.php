<?php

namespace ForkPanelXc\Domain\Module;

interface ModuleInterface
{
    public function getId(): string;
    public function getName(): string;
    public function getVersion(): string;
    public function getDescription(): string;
    public function getAuthor(): string;
    public function getDependencies(): array;
    public function getPermissions(): array;
    public function getRoutes(): array;
    public function getMenuItems(): array;
    public function getSettingsSchema(): array;
    public function onInstall(): bool;
    public function onUpdate(string $fromVersion): bool;
    public function onUninstall(): bool;
    public function onEnable(): bool;
    public function onDisable(): bool;
    public function getCronJobs(): array;
    public function getCliCommands(): array;
    public function getDashboardWidgets(): array;
    public function getApiEndpoints(): array;
}