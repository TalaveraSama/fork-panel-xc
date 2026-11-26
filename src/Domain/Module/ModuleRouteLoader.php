<?php

declare(strict_types=1);

namespace ForkPanelXc\Domain\Module;

use ForkPanelXc\Core\Routing\Router;
use ForkPanelXc\Core\Support\Db;

/**
 * Registers the HTTP routes declared by installed and enabled modules.
 *
 * Modules declare them in module.json under "api_routes":
 *
 *   "api_routes": [
 *     {"method":"GET","path":"/api/tmdb/search","handler":"Vendor\\Module\\Controller@search",
 *      "middleware":["auth","permission:tmdb.use"]}
 *   ]
 *
 * The loader is defensive: if the database is down or a manifest is broken the
 * core routes keep working.
 */
final class ModuleRouteLoader
{
    public static function register(Router $router): void
    {
        try {
            $enabled = array_column(
                Db::select("SELECT module_id FROM modules WHERE status = 'enabled'"),
                'module_id'
            );
        } catch (\Throwable) {
            return;
        }

        if ($enabled === []) {
            return;
        }

        $manifests = (new ModuleManager())->discover();

        foreach ($enabled as $moduleId) {
            $manifest = $manifests[$moduleId] ?? null;

            if ($manifest === null || empty($manifest['api_routes']) || !is_array($manifest['api_routes'])) {
                continue;
            }

            foreach ($manifest['api_routes'] as $route) {
                self::registerRoute($router, $route);
            }
        }
    }

    private static function registerRoute(Router $router, array $route): void
    {
        $method = strtoupper((string) ($route['method'] ?? 'GET'));
        $path = (string) ($route['path'] ?? '');
        $handler = self::resolveHandler((string) ($route['handler'] ?? ''));

        if ($path === '' || $handler === null) {
            return;
        }

        $middleware = array_values(array_filter(
            (array) ($route['middleware'] ?? []),
            static fn ($entry): bool => is_string($entry) && $entry !== ''
        ));

        match ($method) {
            'POST' => $router->post($path, $handler, $middleware),
            'PUT' => $router->put($path, $handler, $middleware),
            'PATCH' => $router->patch($path, $handler, $middleware),
            'DELETE' => $router->delete($path, $handler, $middleware),
            default => $router->get($path, $handler, $middleware),
        };
    }

    private static function resolveHandler(string $handler): ?array
    {
        if (!str_contains($handler, '@')) {
            return null;
        }

        [$class, $method] = explode('@', $handler, 2);
        $class = ltrim($class, '\\');

        if (!class_exists($class) || !method_exists($class, $method)) {
            return null;
        }

        return [$class, $method];
    }
}
