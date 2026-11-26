<?php

declare(strict_types=1);

namespace ForkPanelXc\Public\Controllers\Api;

use ForkPanelXc\Core\Http\ApiRequest;
use ForkPanelXc\Core\Http\ApiResponse;
use ForkPanelXc\Core\Support\Db;

/**
 * Xtream-compatible player API subset.
 */
class PlayerApiController extends BaseApiController
{
    private ?array $line = null;

    public function login(ApiRequest $request): ApiResponse
    {
        $username = (string) $request->query('username', '');
        $password = (string) $request->query('password', '');

        $line = $this->authenticate($username, $password);

        if ($line === null) {
            return ApiResponse::json(['user_info' => ['auth' => 0, 'status' => 'Invalid credentials']]);
        }

        return ApiResponse::json([
            'user_info' => [
                'username' => $line['username'],
                'password' => $password,
                'auth' => 1,
                'status' => 'Active',
                'exp_date' => (string) ($line['exp_date'] ?? ''),
                'is_trial' => '0',
                'active_cons' => (string) ($line['active_cons'] ?? '0'),
                'created_at' => (string) ($line['created_at'] ?? ''),
                'max_connections' => (string) ($line['max_connections'] ?? '1'),
            ],
            'server_info' => [
                'url' => (string) \ForkPanelXc\Core\Support\Env::get('APP_URL', ''),
                'port' => '80',
                'https_port' => '443',
                'server_protocol' => 'http',
                'timezone' => date_default_timezone_get(),
            ],
        ]);
    }

    public function liveCategories(ApiRequest $request): ApiResponse
    {
        $this->line = $this->currentLine($request);

        return ApiResponse::json($this->categories('live'));
    }

    public function vodCategories(ApiRequest $request): ApiResponse
    {
        $this->line = $this->currentLine($request);

        return ApiResponse::json($this->categories('vod'));
    }

    public function seriesCategories(ApiRequest $request): ApiResponse
    {
        $this->line = $this->currentLine($request);

        return ApiResponse::json($this->categories('series'));
    }

    public function liveStreams(ApiRequest $request): ApiResponse
    {
        $this->line = $this->currentLine($request);

        if ($this->line === null) {
            return ApiResponse::json([]);
        }

        $rows = Db::select("SELECT * FROM streams WHERE stream_type = 'live' AND enabled = 1 ORDER BY stream_display_name");

        return ApiResponse::json(array_map(function (array $row): array {
            return [
                'num' => (int) $row['id'],
                'name' => $row['stream_display_name'],
                'stream_type' => 'live',
                'stream_id' => (int) $row['id'],
                'stream_icon' => (string) ($row['stream_icon'] ?? ''),
                'category_id' => (string) ($row['category_id'] ?? ''),
                'epg_channel_id' => (string) ($row['epg_channel_id'] ?? ''),
            ];
        }, $rows));
    }

    public function vodStreams(ApiRequest $request): ApiResponse
    {
        $this->line = $this->currentLine($request);

        if ($this->line === null) {
            return ApiResponse::json([]);
        }

        $rows = Db::select("SELECT * FROM streams WHERE stream_type = 'movie' AND enabled = 1 ORDER BY stream_display_name");

        return ApiResponse::json(array_map(function (array $row): array {
            return [
                'num' => (int) $row['id'],
                'name' => $row['stream_display_name'],
                'stream_type' => 'movie',
                'stream_id' => (int) $row['id'],
                'stream_icon' => (string) ($row['stream_icon'] ?? ''),
                'rating' => '0',
                'year' => (string) ($row['year'] ?? ''),
                'category_id' => (string) ($row['category_id'] ?? ''),
            ];
        }, $rows));
    }

    public function series(ApiRequest $request): ApiResponse
    {
        $this->line = $this->currentLine($request);

        return ApiResponse::json(Db::select('SELECT * FROM series WHERE enabled = 1 ORDER BY name'));
    }

    public function seriesInfo(ApiRequest $request): ApiResponse
    {
        $this->line = $this->currentLine($request);
        $seriesId = (int) $request->query('series_id', 0);

        $series = Db::first('SELECT * FROM series WHERE id = ?', [$seriesId]);

        if ($series === null) {
            return ApiResponse::json(['episodes' => []]);
        }

        $episodes = [];

        foreach (Db::select('SELECT * FROM episodes WHERE series_id = ? ORDER BY season_num, episode_num', [$seriesId]) as $episode) {
            $episodes[(string) $episode['season_num']][] = [
                'id' => (string) $episode['id'],
                'episode_num' => (int) $episode['episode_num'],
                'title' => (string) $episode['title'],
                'container_extension' => 'mp4',
            ];
        }

        return ApiResponse::json([
            'seasons' => array_map(static fn (array $episode): array => [
                'season_number' => (int) $episode['season_num'],
                'name' => 'Season ' . $episode['season_num'],
            ], Db::select('SELECT DISTINCT season_num FROM episodes WHERE series_id = ? ORDER BY season_num', [$seriesId])),
            'info' => $series,
            'episodes' => $episodes,
        ]);
    }

    public function episodeSources(ApiRequest $request): ApiResponse
    {
        $this->line = $this->currentLine($request);
        $episodeId = (int) $request->query('episode_id', 0);
        $episode = Db::first('SELECT * FROM episodes WHERE id = ?', [$episodeId]);

        if ($episode === null || $this->line === null) {
            return ApiResponse::json(['error' => 'Not found']);
        }

        return ApiResponse::json(['sources' => [[
            'url' => (string) $episode['source'],
            'type' => 'mp4',
        ]]]);
    }

    private function categories(string $type): array
    {
        $rows = Db::select('SELECT id, category_name FROM categories WHERE category_type = ? ORDER BY category_name', [$type]);

        return array_map(static fn (array $row): array => [
            'category_id' => (string) $row['id'],
            'category_name' => $row['category_name'],
            'parent_id' => 0,
        ], $rows);
    }

    private function currentLine(ApiRequest $request): ?array
    {
        return $this->authenticate(
            (string) $request->query('username', ''),
            (string) $request->query('password', '')
        );
    }

    private function authenticate(string $username, string $password): ?array
    {
        if ($username === '' || $password === '') {
            return null;
        }

        $line = Db::first('SELECT * FROM lines WHERE username = ? LIMIT 1', [$username]);

        if ($line === null) {
            return null;
        }

        $stored = (string) ($line['password_hash'] ?? $line['password'] ?? '');
        $valid = str_starts_with($stored, '$2y$') || str_starts_with($stored, '$argon2')
            ? password_verify($password, $stored)
            : hash_equals($stored, $password);

        if (!$valid || !(bool) ($line['enabled'] ?? true)) {
            return null;
        }

        return $line;
    }
}
