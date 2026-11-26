<?php

declare(strict_types=1);

namespace ForkPanelXc\Core\Middleware;

use ForkPanelXc\Core\Http\ApiRequest;
use ForkPanelXc\Core\Http\ApiResponse;

/**
 * Fixed-window rate limiter backed by Redis when available, APCu otherwise.
 */
final class RateLimitMiddleware
{
    public function __invoke(ApiRequest $request, int $maxRequests = 60, int $windowSeconds = 60): ?ApiResponse
    {
        $key = sprintf('ratelimit:%s:%s', $request->ip(), $request->path());
        $count = $this->increment($key, $windowSeconds);

        if ($count > $maxRequests) {
            return ApiResponse::error('Rate limit exceeded', 429)->withHeader('Retry-After', (string) $windowSeconds);
        }

        return null;
    }

    private function increment(string $key, int $windowSeconds): int
    {
        if (class_exists(\Redis::class)) {
            try {
                $redis = new \Redis();
                $redis->connect(
                    (string) \ForkPanelXc\Core\Support\Env::get('REDIS_HOST', '127.0.0.1'),
                    (int) \ForkPanelXc\Core\Support\Env::get('REDIS_PORT', 6379),
                    1.0
                );
                $password = \ForkPanelXc\Core\Support\Env::get('REDIS_PASSWORD');
                if ($password !== null && $password !== 'null' && $password !== '') {
                    $redis->auth((string) $password);
                }
                $count = $redis->incr($key);
                if ($count === 1) {
                    $redis->expire($key, $windowSeconds);
                }

                return (int) $count;
            } catch (\Throwable) {
                // fall through to APCu/file-less fallback
            }
        }

        if (function_exists('apcu_inc')) {
            $count = apcu_inc($key, 1, $success);
            if (!$success) {
                apcu_store($key, 1, $windowSeconds);

                return 1;
            }

            return (int) $count;
        }

        // Without shared storage the limiter is a no-op rather than a false positive.
        return 1;
    }
}
