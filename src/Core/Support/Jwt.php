<?php

declare(strict_types=1);

namespace ForkPanelXc\Core\Support;

/**
 * Dependency-free HS256 JWT implementation for the API.
 */
final class Jwt
{
    public static function encode(array $claims, ?string $secret = null): string
    {
        $secret ??= (string) Env::get('JWT_SECRET', 'change-me');

        $header = self::base64Url(json_encode(['typ' => 'JWT', 'alg' => 'HS256']) ?: '{}');
        $payload = self::base64Url(json_encode($claims) ?: '{}');
        $signature = self::base64Url(hash_hmac('sha256', $header . '.' . $payload, $secret, true));

        return $header . '.' . $payload . '.' . $signature;
    }

    public static function decode(string $token, ?string $secret = null): ?array
    {
        $secret ??= (string) Env::get('JWT_SECRET', 'change-me');
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        [$header, $payload, $signature] = $parts;
        $expected = self::base64Url(hash_hmac('sha256', $header . '.' . $payload, $secret, true));

        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $claims = json_decode(self::base64UrlDecode($payload), true);

        if (!is_array($claims)) {
            return null;
        }

        if (isset($claims['exp']) && (int) $claims['exp'] < time()) {
            return null;
        }

        return $claims;
    }

    public static function issue(int $userId, array $extra = []): array
    {
        $ttl = (int) Env::get('JWT_TTL', 60);
        $refreshTtl = (int) Env::get('JWT_REFRESH_TTL', 10080);
        $now = time();

        $access = self::encode(array_merge([
            'sub' => $userId,
            'iat' => $now,
            'exp' => $now + $ttl * 60,
            'typ' => 'access',
        ], $extra));

        $refresh = self::encode([
            'sub' => $userId,
            'iat' => $now,
            'exp' => $now + $refreshTtl * 60,
            'typ' => 'refresh',
        ]);

        return [$access, $refresh];
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
