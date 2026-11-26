<?php

declare(strict_types=1);

namespace ForkPanelXc\Modules\Tmdb\Controllers;

use ForkPanelXc\Core\Http\ApiRequest;
use ForkPanelXc\Core\Http\ApiResponse;
use ForkPanelXc\Core\Support\Db;
use ForkPanelXc\Core\Support\Env;
use ForkPanelXc\Modules\Tmdb\TmdbClient;
use ForkPanelXc\Public\Controllers\Api\BaseApiController;

class TmdbController extends BaseApiController
{
    public function search(ApiRequest $request): ApiResponse
    {
        if (!$this->isAdmin($request)) {
            return ApiResponse::error('Insufficient permissions', 403);
        }

        $query = trim((string) $request->query('query', ''));
        $type = strtolower((string) $request->query('type', 'movie'));
        $type = $type === 'tv' ? 'tv' : 'movie';
        $year = (int) $request->query('year', 0);

        if (mb_strlen($query) < 2) {
            return ApiResponse::error('Enter at least 2 characters to search', 422);
        }

        $client = new TmdbClient();

        if (!$client->hasApiKey()) {
            return ApiResponse::error(
                'TMDB API key is not configured. Set it in Settings → Modules → TMDB or with TMDB_API_KEY.',
                422
            );
        }

        try {
            $results = $client->search($query, $type, $year > 0 ? $year : null);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 502);
        }

        return ApiResponse::json([
            'type' => $type,
            'query' => $query,
            'results' => $results,
        ]);
    }

    public function details(ApiRequest $request, string $type, string $id): ApiResponse
    {
        if (!$this->isAdmin($request)) {
            return ApiResponse::error('Insufficient permissions', 403);
        }

        $type = $type === 'tv' ? 'tv' : 'movie';
        $id = (int) $id;

        if ($id <= 0) {
            return ApiResponse::error('Invalid TMDB id', 422);
        }

        $client = new TmdbClient();

        if (!$client->hasApiKey()) {
            return ApiResponse::error('TMDB API key is not configured', 422);
        }

        try {
            $details = $client->details($type, $id);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 502);
        }

        return ApiResponse::json(['data' => $details]);
    }

    public function getSettings(ApiRequest $request): ApiResponse
    {
        if (!$this->isAdmin($request)) {
            return ApiResponse::error('Insufficient permissions', 403);
        }

        $stored = (string) Db::column(
            "SELECT setting_value FROM settings WHERE section = 'tmdb' AND setting_key = 'api_key'"
        );
        $streaming = (string) Db::column(
            "SELECT setting_value FROM settings WHERE section = 'streaming' AND setting_key = 'tmdb_api_key'"
        );
        $envKey = trim((string) Env::get('TMDB_API_KEY', ''));
        $key = $envKey !== '' ? $envKey : (trim($stored) !== '' ? trim($stored) : trim($streaming));

        return ApiResponse::json([
            'data' => [
                'has_key' => $key !== '',
                'key_masked' => $key !== '' ? substr($key, 0, 4) . str_repeat('•', 12) . substr($key, -4) : '',
                'source' => $envKey !== '' ? 'env' : (($stored !== '' || $streaming !== '') ? 'database' : 'none'),
                'language' => TmdbClient::storedLanguage(),
            ],
        ]);
    }

    public function saveSettings(ApiRequest $request): ApiResponse
    {
        if (!$this->isAdmin($request)) {
            return ApiResponse::error('Insufficient permissions', 403);
        }

        $data = $request->json();
        $key = trim((string) ($data['api_key'] ?? ''));
        $language = trim((string) ($data['language'] ?? ''));

        if ($key !== '' && strlen($key) < 16) {
            return ApiResponse::error('That API key looks too short', 422);
        }

        if ($key !== '') {
            $probe = new TmdbClient($key);

            if (!$probe->validateKey()) {
                return ApiResponse::error('TMDB rejected the API key', 422);
            }

            $this->upsert('api_key', $key);
        }

        if ($language !== '') {
            if (!preg_match('/^[a-z]{2}(-[A-Z]{2})?$/', $language)) {
                return ApiResponse::error('Language must look like es-ES or en-US', 422);
            }

            $this->upsert('language', $language);
        }

        return $this->getSettings($request);
    }

    private function upsert(string $key, string $value): void
    {
        Db::execute(
            'INSERT INTO settings (section, setting_key, setting_value, updated_at) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = VALUES(updated_at)',
            ['tmdb', $key, $value, time()]
        );
    }

    private function isAdmin(ApiRequest $request): bool
    {
        $user = $request->user();

        return $user !== null && $user->hasRole('admin');
    }
}
