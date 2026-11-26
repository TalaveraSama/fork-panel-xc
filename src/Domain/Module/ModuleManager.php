<?php

declare(strict_types=1);

namespace ForkPanelXc\Domain\Module;

use ForkPanelXc\Core\Support\Db;

/**
 * Discovers bundled modules from src/Modules/<Name>/module.json and tracks
 * their install state in the `modules` table.
 */
final class ModuleManager
{
    private string $modulesPath;

    public function __construct(?string $modulesPath = null)
    {
        $this->modulesPath = $modulesPath ?? dirname(__DIR__, 2) . '/Modules';
    }

    public function discover(): array
    {
        $modules = [];

        foreach (glob($this->modulesPath . '/*/module.json') ?: [] as $manifestPath) {
            $manifest = json_decode((string) file_get_contents($manifestPath), true);

            if (!is_array($manifest) || empty($manifest['id'])) {
                continue;
            }

            $manifest['path'] = dirname($manifestPath);
            $modules[$manifest['id']] = $manifest;
        }

        return $modules;
    }

    public function installed(): array
    {
        return Db::select('SELECT * FROM modules ORDER BY name');
    }

    public function install(string $moduleId): bool
    {
        $module = $this->discover()[$moduleId] ?? null;

        if ($module === null) {
            return false;
        }

        Db::execute(
            'INSERT INTO modules (module_id, name, version, description, author, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE version = VALUES(version), status = VALUES(status), updated_at = VALUES(updated_at)',
            [
                $module['id'],
                $module['name'] ?? $module['id'],
                $module['version'] ?? '1.0.0',
                $module['description'] ?? '',
                $module['author'] ?? '',
                'enabled',
                time(),
                time(),
            ]
        );

        return true;
    }

    public function uninstall(string $moduleId): bool
    {
        return Db::execute('DELETE FROM modules WHERE module_id = ?', [$moduleId]) > 0;
    }

    public function setStatus(string $moduleId, string $status): bool
    {
        return Db::execute('UPDATE modules SET status = ?, updated_at = ? WHERE module_id = ?', [$status, time(), $moduleId]) > 0;
    }

    public function status(string $moduleId): ?string
    {
        $value = Db::column('SELECT status FROM modules WHERE module_id = ?', [$moduleId]);

        return $value === false || $value === null ? null : (string) $value;
    }
}
