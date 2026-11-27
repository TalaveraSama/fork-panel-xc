<?php

declare(strict_types=1);

namespace ForkPanelXc\Core\Support;

/**
 * Short-lived signed tokens for the stream gateway.
 *
 * Payload: l=line id, s=stream id, u=absolute URL, k=playlist|media, sid=session.
 */
final class ProxyToken
{
    private const TTL = 43200;

    public static function issue(array $payload): string
    {
        $secret = (string) Env::get('STREAM_TOKEN_SECRET', 'change-me');
        $payload['exp'] = time() + self::TTL;

        $encoded = self::base64Url(json_encode($payload) ?: '{}');
        $signature = self::base64Url(hash_hmac('sha256', $encoded, $secret, true));

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
        $expected = self::base64Url(hash_hmac('sha256', $encoded, $secret, true));

        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $payload = json_decode(self::base64UrlDecode($encoded), true);

        if (!is_array($payload) || (int) ($payload['exp'] ?? 0) < time()) {
            return null;
        }

        if (empty($payload['u']) || empty($payload['l']) || empty($payload['s'])) {
            return null;
        }

        return $payload;
    }

    private static function base64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/')) ?: '';
    }
}
