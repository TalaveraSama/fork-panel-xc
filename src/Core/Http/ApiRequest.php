<?php

declare(strict_types=1);

namespace ForkPanelXc\Core\Http;

/**
 * HTTP request wrapper used by the Fork Panel XC JSON API.
 *
 * This is intentionally separate from the legacy `XcVm\Core\Http\Request`
 * sanitizer used by the panel, so both can coexist.
 */
class ApiRequest
{
    private array $query;
    private array $post;
    private array $json;
    private array $files;
    private array $headers;
    private string $method;
    private string $uri;
    private ?object $user = null;
    private array $attributes = [];

    public function __construct()
    {
        $this->query = $_GET ?? [];
        $this->post = $_POST ?? [];
        $this->files = $_FILES ?? [];
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->uri = $_SERVER['REQUEST_URI'] ?? '/';
        $this->headers = $this->parseHeaders();
        $this->json = $this->parseJsonBody();
    }

    private function parseHeaders(): array
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$name] = $value;
            }
        }

        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['Content-Type'] = $_SERVER['CONTENT_TYPE'];
        }

        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $headers['Content-Length'] = $_SERVER['CONTENT_LENGTH'];
        }

        return $headers;
    }

    private function parseJsonBody(): array
    {
        if (!in_array($this->method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return [];
        }

        $contentType = $this->headers['Content-Type'] ?? '';

        if (str_contains($contentType, 'application/json')) {
            $raw = (string) file_get_contents('php://input');
            $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw; // strip UTF-8 BOM
            $decoded = json_decode($raw ?: '[]', true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function queryAll(): array
    {
        return $this->query;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $this->json[$key] ?? $this->post[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->post, $this->json);
    }

    public function json(): array
    {
        return $this->json;
    }

    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function header(string $key, mixed $default = null): mixed
    {
        return $this->headers[$key] ?? $default;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function uri(): string
    {
        return $this->uri;
    }

    public function path(): string
    {
        return parse_url($this->uri, PHP_URL_PATH) ?: '/';
    }

    public function bearerToken(): ?string
    {
        $auth = $this->headers['Authorization'] ?? '';

        return str_starts_with($auth, 'Bearer ') ? substr($auth, 7) : null;
    }

    public function setUser(object $user): void
    {
        $this->user = $user;
    }

    public function user(): ?object
    {
        return $this->user;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function attributes(): array
    {
        return $this->attributes;
    }

    public function ip(): string
    {
        return $this->headers['X-Forwarded-For']
            ?? $this->headers['X-Real-IP']
            ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    }
}
