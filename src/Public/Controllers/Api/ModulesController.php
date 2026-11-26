<?php

declare(strict_types=1);

namespace ForkPanelXc\Public\Controllers\Api;

use ForkPanelXc\Core\Http\ApiRequest;
use ForkPanelXc\Core\Http\ApiResponse;
use ForkPanelXc\Domain\Module\ModuleManager;

class ModulesController extends BaseApiController
{
    public function __construct(private ?ModuleManager $modules = null)
    {
        $this->modules ??= new ModuleManager();
    }

    public function index(ApiRequest $request): ApiResponse
    {
        $discovered = $this->modules->discover();
        $installed = $this->modules->installed();
        $installedIds = array_column($installed, 'module_id');

        $available = array_values(array_filter(
            $discovered,
            static fn (array $module): bool => !in_array($module['id'], $installedIds, true)
        ));

        return ApiResponse::json([
            'installed' => array_map(static fn (array $row): array => [
                'id' => $row['module_id'],
                'name' => $row['name'],
                'version' => $row['version'],
                'description' => $row['description'],
                'status' => $row['status'],
            ], $installed),
            'available' => array_map(static fn (array $module): array => [
                'id' => $module['id'],
                'name' => $module['name'] ?? $module['id'],
                'version' => $module['version'] ?? '1.0.0',
                'description' => $module['description'] ?? '',
            ], $available),
        ]);
    }

    public function show(ApiRequest $request, string $id): ApiResponse
    {
        $module = $this->modules->discover()[$id] ?? null;

        if ($module === null) {
            return ApiResponse::error('Module not found', 404);
        }

        $module['status'] = $this->modules->status($id) ?? 'available';
        $module['permissions'] = array_map(
            static fn (array $permission): string => (string) ($permission['id'] ?? ''),
            $module['permissions'] ?? []
        );

        return ApiResponse::json(['data' => $module]);
    }

    public function install(ApiRequest $request, string $id): ApiResponse
    {
        return $this->modules->install($id)
            ? ApiResponse::json(['success' => true])
            : ApiResponse::error('Module not found', 404);
    }

    public function update(ApiRequest $request, string $id): ApiResponse
    {
        $module = $this->modules->discover()[$id] ?? null;

        if ($module === null) {
            return ApiResponse::error('Module not found', 404);
        }

        return ApiResponse::json(['success' => true, 'version' => $module['version'] ?? '1.0.0']);
    }

    public function uninstall(ApiRequest $request, string $id): ApiResponse
    {
        return $this->modules->uninstall($id)
            ? ApiResponse::json(['success' => true])
            : ApiResponse::error('Module not installed', 404);
    }

    public function enable(ApiRequest $request, string $id): ApiResponse
    {
        return $this->modules->setStatus($id, 'enabled')
            ? ApiResponse::json(['success' => true])
            : ApiResponse::error('Module not installed', 404);
    }

    public function disable(ApiRequest $request, string $id): ApiResponse
    {
        return $this->modules->setStatus($id, 'disabled')
            ? ApiResponse::json(['success' => true])
            : ApiResponse::error('Module not installed', 404);
    }
}
