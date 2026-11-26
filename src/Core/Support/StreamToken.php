<?php

declare(strict_types=1);

namespace ForkPanelXc\Core\Support;

/**
 * Signed, short-lived tokens for stream URLs and playlist downloads.
 */
final class StreamToken
{
    public static function issue(int $lineId, int $streamId, string $kind = 'live'): string
    {
        $secret = (string) Env::get('STREAM_TOKEN_SECRET', 'change-me');
        $ttl = (int) Env::get('STREAM_TOKEN_TTL', 3600);

        $payload = json_encode([
            'line' => $lineId,
            'stream' => $streamId,
            'kind' => $kind,
            'exp' => time() + $ttl,
        ]) ?: '{}';

        $encoded = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
        $signature = rtrim(strtr(base64_encode(hash_hmac('sha256', $encoded, $secret, true)), '+/', '-_'), '=');

        return $encoded . '.' . $signature;
    }

    public static function parse(string $token): ?array
    {
        $secret = (string) Env::get('STREAM_TOKEN_SECRET', 'change-me');
        $parts = explode('.', $token);

        if (count($parts) !== 2) {
            return null;
        }

        [$encoded, $signature] = $parts;
        $expected = rtrim(strtr(base64_encode(hash_hmac('sha256', $encoded, $secret, true)), '+/', '-_'), '=');

        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $payload = json_decode((string) base64_decode(strtr($encoded, '-_', '+/')), true);

        if (!is_array($payload) || (int) ($payload['exp'] ?? 0) < time()) {
            return null;
        }

        return $payload;
    }
}
