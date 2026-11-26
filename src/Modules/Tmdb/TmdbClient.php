<?php

declare(strict_types=1);

namespace ForkPanelXc\Modules\Tmdb;

use ForkPanelXc\Core\Support\Db;
use ForkPanelXc\Core\Support\Env;

/**
 * Minimal TMDB v3 client with a small on-disk cache.
 */
final class TmdbClient
{
    private const API_BASE = 'https://api.themoviedb.org/3';
    private const IMAGE_BASE = 'https://image.tmdb.org/t/p';
    private const POSTER_SIZE = 'w500';
    private const BACKDROP_SIZE = 'w1280';
    private const CACHE_TTL = 86400;

    private string $language;
    private string $apiKey;

    public function __construct(?string $apiKey = null, ?string $language = null)
    {
        $this->apiKey = $apiKey ?? self::storedApiKey();
        $this->language = $language ?? self::storedLanguage();
    }

    public static function storedApiKey(): string
    {
        $envKey = trim((string) Env::get('TMDB_API_KEY', ''));

        if ($envKey !== '') {
            return $envKey;
        }

        try {
            $value = Db::column(
                "SELECT setting_value FROM settings WHERE section = 'tmdb' AND setting_key = 'api_key'"
            );

            if ($value !== false && $value !== null && trim((string) $value) !== '') {
                return trim((string) $value);
            }

            // Fallback: key saved through Settings → Streaming (core settings).
            $value = Db::column(
                "SELECT setting_value FROM settings WHERE section = 'streaming' AND setting_key = 'tmdb_api_key'"
            );
        } catch (\Throwable) {
            return '';
        }

        return $value === false || $value === null ? '' : trim((string) $value);
    }

    public static function storedLanguage(): string
    {
        try {
            $value = Db::column(
                "SELECT setting_value FROM settings WHERE section = 'tmdb' AND setting_key = 'language'"
            );

            if ($value === false || $value === null || trim((string) $value) === '') {
                $value = Db::column(
                    "SELECT setting_value FROM settings WHERE section = 'streaming' AND setting_key = 'tmdb_language'"
                );
            }
        } catch (\Throwable) {
            return 'es-ES';
        }

        $value = $value === false || $value === null ? '' : trim((string) $value);

        return $value !== '' ? $value : 'es-ES';
    }

    public function hasApiKey(): bool
    {
        return $this->apiKey !== '';
    }

    public function search(string $query, string $type = 'movie', ?int $year = null): array
    {
        $type = $type === 'tv' ? 'tv' : 'movie';
        $query = trim($query);

        if ($query === '') {
            return [];
        }

        $params = [
            'query' => $query,
            'language' => $this->language,
            'include_adult' => 'false',
        ];

        if ($year !== null && $year > 0) {
            $params[$type === 'tv' ? 'first_air_date_year' : 'year'] = (string) $year;
        }

        $payload = $this->request('/search/' . $type, $params);

        return array_map(
            fn (array $item): array => $this->normalize($item, $type),
            array_slice($payload['results'] ?? [], 0, 20)
        );
    }

    public function details(string $type, int $id): array
    {
        $type = $type === 'tv' ? 'tv' : 'movie';
        $payload = $this->request(sprintf('/%s/%d', $type, $id), ['language' => $this->language]);

        $normalized = $this->normalize($payload, $type);
        $normalized['genres'] = array_map(
            static fn (array $genre): string => (string) ($genre['name'] ?? ''),
            $payload['genres'] ?? []
        );
        $normalized['runtime'] = (int) ($payload['runtime'] ?? ($payload['episode_run_time'][0] ?? 0));
        $normalized['tagline'] = (string) ($payload['tagline'] ?? '');
        $normalized['status'] = (string) ($payload['status'] ?? '');
        $normalized['seasons'] = (int) ($payload['number_of_seasons'] ?? 0);
        $normalized['episodes'] = (int) ($payload['number_of_episodes'] ?? 0);

        return $normalized;
    }

    public function validateKey(): bool
    {
        if (!$this->hasApiKey()) {
            return false;
        }

        try {
            $this->request('/configuration', [], false);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function normalize(array $item, string $type): array
    {
        $title = $type === 'tv'
            ? ($item['name'] ?? $item['original_name'] ?? '')
            : ($item['title'] ?? $item['original_title'] ?? '');

        $date = (string) ($item['release_date'] ?? $item['first_air_date'] ?? '');

        return [
            'id' => (int) ($item['id'] ?? 0),
            'type' => $type,
            'title' => (string) $title,
            'original_title' => (string) ($item['original_title'] ?? $item['original_name'] ?? $title),
            'overview' => (string) ($item['overview'] ?? ''),
            'year' => $date !== '' ? (int) substr($date, 0, 4) : null,
            'release_date' => $date,
            'poster_url' => !empty($item['poster_path'])
                ? self::IMAGE_BASE . '/' . self::POSTER_SIZE . $item['poster_path']
                : '',
            'backdrop_url' => !empty($item['backdrop_path'])
                ? self::IMAGE_BASE . '/' . self::BACKDROP_SIZE . $item['backdrop_path']
                : '',
            'rating' => round((float) ($item['vote_average'] ?? 0), 1),
            'popularity' => round((float) ($item['popularity'] ?? 0), 2),
        ];
    }

    private function request(string $path, array $params = [], bool $useCache = true): array
    {
        if (!$this->hasApiKey()) {
            throw new \RuntimeException('TMDB API key is not configured');
        }

        $params['api_key'] = $this->apiKey;
        $url = self::API_BASE . $path . '?' . http_build_query($params);
        $cacheFile = $useCache ? $this->cachePath($url) : null;

        if ($cacheFile !== null && is_file($cacheFile) && (time() - (int) filemtime($cacheFile)) < self::CACHE_TTL) {
            $cached = json_decode((string) file_get_contents($cacheFile), true);

            if (is_array($cached)) {
                return $cached;
            }
        }

        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'ForkPanelXC/1.0 TMDB module',
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);

        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if ($body === false || $error !== '') {
            throw new \RuntimeException('TMDB request failed: ' . $error);
        }

        $payload = json_decode((string) $body, true);

        if (!is_array($payload)) {
            throw new \RuntimeException('TMDB returned an invalid response');
        }

        if ($status === 401) {
            throw new \RuntimeException('TMDB rejected the API key');
        }

        if ($status >= 400) {
            throw new \RuntimeException('TMDB error: ' . ($payload['status_message'] ?? 'HTTP ' . $status));
        }

        if ($cacheFile !== null) {
            $directory = dirname($cacheFile);

            if (!is_dir($directory)) {
                @mkdir($directory, 0775, true);
            }

            if (is_dir($directory) && is_writable($directory)) {
                @file_put_contents($cacheFile, json_encode($payload), LOCK_EX);
            }
        }

        return $payload;
    }

    private function cachePath(string $url): string
    {
        $base = (string) Env::get('CACHE_PATH', dirname(__DIR__, 3) . '/storage/cache');

        return rtrim($base, '/') . '/tmdb/' . sha1($url) . '.json';
    }
}
