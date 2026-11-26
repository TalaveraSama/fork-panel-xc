<?php

declare(strict_types=1);

namespace ForkPanelXc\Core\Routing;

use ForkPanelXc\Core\Http\ApiRequest;
use ForkPanelXc\Core\Http\ApiResponse;

/**
 * Small regex router for the Fork Panel XC API.
 *
 * Handlers are [Controller::class, 'method'] pairs or closures. Middleware
 * entries may be class names (invoked with the request) or
 * "Class:arg1,arg2" strings.
 */
final class Router
{
    /** @var array<int, array{method:string,path:string,pattern:string,handler:mixed,middleware:array}> */
    private array $routes = [];

    private array $groupStack = [];

    public function get(string $path, mixed $handler, array $middleware = []): self
    {
        return $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, mixed $handler, array $middleware = []): self
    {
        return $this->add('POST', $path, $handler, $middleware);
    }

    public function put(string $path, mixed $handler, array $middleware = []): self
    {
        return $this->add('PUT', $path, $handler, $middleware);
    }

    public function patch(string $path, mixed $handler, array $middleware = []): self
    {
        return $this->add('PATCH', $path, $handler, $middleware);
    }

    public function delete(string $path, mixed $handler, array $middleware = []): self
    {
        return $this->add('DELETE', $path, $handler, $middleware);
    }

    public function group(string $prefix, callable $callback, array $middleware = []): void
    {
        $this->groupStack[] = ['prefix' => $prefix, 'middleware' => $middleware];
        $callback($this);
        array_pop($this->groupStack);
    }

    private function add(string $method, string $path, mixed $handler, array $middleware): self
    {
        $fullPath = $this->prefix() . $path;
        $fullPath = '/' . ltrim($fullPath, '/');
        $fullPath = rtrim($fullPath, '/') ?: '/';

        $this->routes[] = [
            'method' => $method,
            'path' => $fullPath,
            'pattern' => $this->compile($fullPath),
            'handler' => $handler,
            'middleware' => array_merge($this->groupMiddleware(), $middleware),
        ];

        return $this;
    }

    private function prefix(): string
    {
        $prefix = '';

        foreach ($this->groupStack as $group) {
            $prefix .= $group['prefix'];
        }

        return $prefix;
    }

    private function groupMiddleware(): array
    {
        $middleware = [];

        foreach ($this->groupStack as $group) {
            $middleware = array_merge($middleware, $group['middleware']);
        }

        return $middleware;
    }

    private function compile(string $path): string
    {
        $pattern = preg_replace('/\{([A-Za-z_][A-Za-z0-9_]*)\}/', '(?P<$1>[^/]+)', $path);

        return '#^' . $pattern . '$#';
    }

    public function dispatch(ApiRequest $request): ApiResponse
    {
        $path = rtrim($request->path(), '/');
        $path = $path === '' ? '/' : $path;
        $method = $request->method();
        $allowed = [];

        foreach ($this->routes as $route) {
            if (!preg_match($route['pattern'], $path, $matches)) {
                continue;
            }

            if ($route['method'] !== $method) {
                $allowed[$route['method']] = true;
                continue;
            }

            $params = [];

            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }

            foreach ($route['middleware'] as $middleware) {
                $result = $this->runMiddleware($middleware, $request);

                if ($result instanceof ApiResponse) {
                    return $result;
                }
            }

            return $this->invoke($route['handler'], $request, $params);
        }

        if ($allowed !== []) {
            return ApiResponse::error('Method not allowed', 405)->withHeader('Allow', implode(', ', array_keys($allowed)));
        }

        return ApiResponse::error('Not found', 404);
    }

    private function runMiddleware(string $middleware, ApiRequest $request): ?ApiResponse
    {
        $aliases = [
            'auth' => \ForkPanelXc\Core\Middleware\AuthMiddleware::class,
            'permission' => \ForkPanelXc\Core\Middleware\PermissionMiddleware::class,
            'rate_limit' => \ForkPanelXc\Core\Middleware\RateLimitMiddleware::class,
            'player_auth' => \ForkPanelXc\Core\Middleware\PlayerAuthMiddleware::class,
            'panel_auth' => \ForkPanelXc\Core\Middleware\PanelAuthMiddleware::class,
        ];

        [$name, $args] = array_pad(explode(':', $middleware, 2), 2, '');

        if (isset($aliases[$name])) {
            $class = $aliases[$name];
        } else {
            $class = $name;
        }

        $arguments = $args === '' ? [] : array_map(static function (string $argument): int|string {
            $argument = trim($argument);

            return ctype_digit($argument) ? (int) $argument : $argument;
        }, explode(',', $args));

        if (!class_exists($class)) {
            return ApiResponse::error(sprintf('Middleware %s not found', $class), 500);
        }

        $instance = new $class();

        if (!is_callable($instance)) {
            return ApiResponse::error(sprintf('Middleware %s is not callable', $class), 500);
        }

        return $instance($request, ...$arguments);
    }

    private function invoke(mixed $handler, ApiRequest $request, array $params): ApiResponse
    {
        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;

            if (!class_exists($class) || !method_exists($class, $method)) {
                return ApiResponse::error('Handler not found', 500);
            }

            $instance = new $class();

            return $instance->$method($request, ...array_values($params));
        }

        if (is_callable($handler)) {
            return $handler($request, ...array_values($params));
        }

        return ApiResponse::error('Invalid handler', 500);
    }
}
