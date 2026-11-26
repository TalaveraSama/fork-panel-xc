<?php

declare(strict_types=1);

namespace ForkPanelXc\Public\Controllers\Api;

use ForkPanelXc\Core\Http\ApiRequest;
use ForkPanelXc\Core\Http\ApiResponse;
use ForkPanelXc\Core\Support\Db;
use ForkPanelXc\Core\Support\Env;

class ToolsController extends BaseApiController
{
    public function diagnostics(ApiRequest $request): ApiResponse
    {
        return ApiResponse::json(['data' => [
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'cli',
            'database' => $this->databaseStatus(),
            'redis' => $this->redisStatus(),
            'storage_writable' => is_writable((string) Env::get('STORAGE_PATH', dirname(__DIR__, 4) . '/storage')),
            'disk_free' => (string) @disk_free_space('/'),
            'load_average' => function_exists('sys_getloadavg') ? implode(' ', array_map('strval', sys_getloadavg() ?: [])) : 'n/a',
            'uptime' => $this->uptime(),
        ]]);
    }

    public function testStream(ApiRequest $request): ApiResponse
    {
        $url = (string) ($request->json()['url'] ?? '');

        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            return ApiResponse::error('A valid URL is required', 422);
        }

        $start = microtime(true);
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'ForkPanelXC/1.0',
        ]);

        curl_exec($handle);
        $code = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $error = curl_error($handle);
        $type = (string) curl_getinfo($handle, CURLINFO_CONTENT_TYPE);
        curl_close($handle);

        return ApiResponse::json(['data' => [
            'success' => $error === '' && $code >= 200 && $code < 400,
            'latency' => (int) round((microtime(true) - $start) * 1000),
            'http_code' => $code,
            'content_type' => $type,
            'error' => $error !== '' ? $error : ($code >= 400 ? 'HTTP ' . $code : null),
        ]]);
    }

    public function database(ApiRequest $request): ApiResponse
    {
        $tables = (int) Db::column(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()"
        );

        return ApiResponse::json(['data' => [
            'status' => $this->databaseStatus(),
            'database' => (string) Env::get('DB_DATABASE', ''),
            'tables' => $tables,
            'migrations' => $this->migrationStatus(),
        ]]);
    }

    public function runMigrations(ApiRequest $request): ApiResponse
    {
        $directory = dirname(__DIR__, 3) . '/Api/migrations';
        $applied = [];
        $failed = [];

        Db::pdo()->exec('CREATE TABLE IF NOT EXISTS migrations (
            name VARCHAR(190) PRIMARY KEY,
            applied_at BIGINT NOT NULL
        )');

        foreach (glob($directory . '/*.sql') ?: [] as $file) {
            $name = basename($file);

            if (Db::first('SELECT name FROM migrations WHERE name = ?', [$name]) !== null) {
                continue;
            }

            try {
                $sql = (string) file_get_contents($file);

                foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
                    Db::pdo()->exec($statement);
                }

                Db::execute('INSERT INTO migrations (name, applied_at) VALUES (?, ?)', [$name, time()]);
                $applied[] = $name;
            } catch (\Throwable $e) {
                $failed[$name] = $e->getMessage();
            }
        }

        return ApiResponse::json(['data' => ['applied' => $applied, 'failed' => $failed]]);
    }

    public function cache(ApiRequest $request): ApiResponse
    {
        $path = (string) Env::get('CACHE_PATH', dirname(__DIR__, 4) . '/storage/cache');
        $files = is_dir($path) ? (glob($path . '/*') ?: []) : [];
        $bytes = 0;

        foreach ($files as $file) {
            $bytes += is_file($file) ? (int) filesize($file) : 0;
        }

        return ApiResponse::json(['data' => [
            'files' => count($files),
            'size' => $bytes,
            'path' => $path,
            'redis' => $this->redisStatus(),
        ]]);
    }

    public function clearCache(ApiRequest $request): ApiResponse
    {
        $path = (string) Env::get('CACHE_PATH', dirname(__DIR__, 4) . '/storage/cache');
        $removed = 0;

        foreach (is_dir($path) ? (glob($path . '/*') ?: []) : [] as $file) {
            if (is_file($file) && @unlink($file)) {
                $removed++;
            }
        }

        return ApiResponse::json(['data' => ['removed' => $removed]]);
    }

    private function databaseStatus(): string
    {
        try {
            Db::pdo()->query('SELECT 1');

            return 'ok';
        } catch (\Throwable $e) {
            return 'error: ' . $e->getMessage();
        }
    }

    private function redisStatus(): string
    {
        if (!class_exists(\Redis::class)) {
            return 'extension missing';
        }

        try {
            $redis = new \Redis();
            $redis->connect((string) Env::get('REDIS_HOST', '127.0.0.1'), (int) Env::get('REDIS_PORT', 6379), 1.0);

            return 'ok';
        } catch (\Throwable $e) {
            return 'error: ' . $e->getMessage();
        }
    }

    private function migrationStatus(): array
    {
        $files = array_map('basename', glob(dirname(__DIR__, 3) . '/Api/migrations/*.sql') ?: []);
        $applied = array_column(Db::select('SELECT name FROM migrations'), 'name');

        return [
            'total' => count($files),
            'applied' => count($applied),
            'pending' => array_values(array_diff($files, $applied)),
        ];
    }

    private function uptime(): string
    {
        if (!is_readable('/proc/uptime')) {
            return 'unknown';
        }

        $seconds = (int) explode(' ', (string) file_get_contents('/proc/uptime'))[0];
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);

        return sprintf('%dd %dh', $days, $hours);
    }
}
