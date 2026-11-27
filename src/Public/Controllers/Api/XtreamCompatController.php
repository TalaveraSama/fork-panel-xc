<?php

declare(strict_types=1);

namespace ForkPanelXc\Public\Controllers\Api;

use ForkPanelXc\Core\Http\ApiRequest;
use ForkPanelXc\Core\Http\ApiResponse;
use ForkPanelXc\Core\Support\Db;
use ForkPanelXc\Core\Support\ProxyToken;
use ForkPanelXc\Domain\Stream\StreamProxy;

/**
 * Xtream Codes compatible surface used by IPTV players (IPTV Smarters,
 * XCIPTV, TiviMate, …).
 *
 * Endpoints:
 *   /player_api.php?username=&password=&action=…
 *   /get.php?username=&password=&type=m3u_plus&output=ts
 *   /xmltv.php?username=&password=
 *   /live/{username}/{password}/{id}.{ts|m3u8}
 *   /movie/{username}/{password}/{id}.{ext}
 *   /series/{username}/{password}/{episode_id}.{ext}
 */
class XtreamCompatController extends BaseApiController
{
    public function playerApi(ApiRequest $request): ApiResponse
    {
        $username = (string) $request->query('username', '');
        $password = (string) $request->query('password', '');
        $action = (string) $request->query('action', '');
        $line = $this->authenticate($username, $password);

        if ($line === null) {
            return ApiResponse::json([
                'user_info' => [
                    'auth' => 0,
                    'status' => 'Invalid credentials',
                    'message' => 'Invalid username or password',
                ],
            ]);
        }

        if ($action === '') {
            return ApiResponse::json($this->loginPayload($request, $line, $password));
        }

        return match ($action) {
            'get_live_categories' => ApiResponse::json($this->categories('live')),
            'get_vod_categories' => ApiResponse::json($this->categories('vod')),
            'get_series_categories' => ApiResponse::json($this->categories('series')),
            'get_live_streams' => ApiResponse::json($this->liveStreams()),
            'get_vod_streams' => ApiResponse::json($this->vodStreams()),
            'get_vod_info' => ApiResponse::json($this->vodInfo((int) $request->query('vod_id', 0))),
            'get_series' => ApiResponse::json($this->seriesList()),
            'get_series_info' => ApiResponse::json($this->seriesInfo((int) $request->query('series_id', 0))),
            'get_short_epg' => ApiResponse::json(['epg_listings' => []]),
            'get_simple_data_table' => ApiResponse::json(['epg_listings' => []]),
            'get_epg' => ApiResponse::json(['epg_listings' => []]),
            default => ApiResponse::json([]),
        };
    }

    public function playlist(ApiRequest $request): ApiResponse
    {
        $username = (string) $request->query('username', '');
        $password = (string) $request->query('password', '');
        $line = $this->authenticate($username, $password);

        if ($line === null) {
            return ApiResponse::error('Not found', 404);
        }

        $base = $this->baseUrl($request);
        $lines = ['#EXTM3U'];

        foreach ($this->liveRows() as $row) {
            $extension = str_contains(strtolower((string) $row['stream_source']), '.m3u8') ? 'm3u8' : 'ts';
            $attributes = sprintf(
                'tvg-id="%s" tvg-name="%s" tvg-logo="%s" group-title="%s"',
                htmlspecialchars((string) ($row['epg_channel_id'] ?? ''), ENT_QUOTES),
                htmlspecialchars((string) $row['stream_display_name'], ENT_QUOTES),
                htmlspecialchars((string) ($row['stream_icon'] ?? ''), ENT_QUOTES),
                htmlspecialchars((string) ($row['category_name'] ?? 'Live'), ENT_QUOTES)
            );

            $lines[] = sprintf(
                '#EXTINF:-1 %s,%s',
                $attributes,
                $row['stream_display_name']
            );
            $lines[] = sprintf(
                '%s/live/%s/%s/%d.%s',
                $base,
                rawurlencode($username),
                rawurlencode($password),
                (int) $row['id'],
                $extension
            );
        }

        foreach ($this->vodRows() as $row) {
            $extension = pathinfo((string) $row['stream_source'], PATHINFO_EXTENSION) ?: 'mp4';
            $lines[] = sprintf(
                '#EXTINF:-1 tvg-id="" tvg-name="%s" tvg-logo="%s" group-title="%s",%s',
                htmlspecialchars((string) $row['stream_display_name'], ENT_QUOTES),
                htmlspecialchars((string) ($row['stream_icon'] ?? ''), ENT_QUOTES),
                htmlspecialchars((string) ($row['category_name'] ?? 'VOD'), ENT_QUOTES),
                $row['stream_display_name']
            );
            $lines[] = sprintf(
                '%s/movie/%s/%s/%d.%s',
                $base,
                rawurlencode($username),
                rawurlencode($password),
                (int) $row['id'],
                $extension
            );
        }

        return ApiResponse::raw(implode("\n", $lines) . "\n", 'audio/x-mpegurl; charset=utf-8');
    }

    public function xmltv(ApiRequest $request): ApiResponse
    {
        $line = $this->authenticate(
            (string) $request->query('username', ''),
            (string) $request->query('password', '')
        );

        if ($line === null) {
            return ApiResponse::error('Not found', 404);
        }

        $channels = Db::select(
            "SELECT id, stream_display_name AS name, epg_channel_id, stream_icon AS icon
             FROM streams WHERE stream_type = 'live' AND enabled = 1 ORDER BY stream_display_name"
        );
        $programmes = Db::select(
            'SELECT p.*, c.epg_id, c.name AS channel_name FROM epg_programmes p
             JOIN epg_channels c ON c.id = p.channel_id
             WHERE p.end_time >= ? ORDER BY p.start_time ASC LIMIT 5000',
            [time()]
        );

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<tv generator-info-name="Fork Panel XC">' . "\n";

        foreach ($channels as $channel) {
            $xml .= sprintf(
                "  <channel id=\"%s\"><display-name>%s</display-name><icon src=\"%s\"/></channel>\n",
                htmlspecialchars((string) ($channel['epg_channel_id'] ?: $channel['name']), ENT_XML1),
                htmlspecialchars((string) $channel['name'], ENT_XML1),
                htmlspecialchars((string) ($channel['icon'] ?? ''), ENT_XML1)
            );
        }

        foreach ($programmes as $programme) {
            $xml .= sprintf(
                "  <programme start=\"%s\" stop=\"%s\" channel=\"%s\"><title>%s</title><desc>%s</desc></programme>\n",
                date('YmdHis O', (int) $programme['start_time']),
                date('YmdHis O', (int) $programme['end_time']),
                htmlspecialchars((string) $programme['epg_id'], ENT_XML1),
                htmlspecialchars((string) $programme['title'], ENT_XML1),
                htmlspecialchars((string) ($programme['description'] ?? ''), ENT_XML1)
            );
        }

        $xml .= '</tv>';

        return ApiResponse::raw($xml, 'application/xml; charset=utf-8');
    }

    public function live(ApiRequest $request, string $username, string $password, string $file): ApiResponse
    {
        $line = $this->authenticate($username, $password);

        if ($line === null) {
            return ApiResponse::error('Not found', 404);
        }

        $stream = Db::first(
            "SELECT * FROM streams WHERE id = ? AND stream_type = 'live' AND enabled = 1",
            [$this->fileId($file)]
        );

        if ($stream === null || empty($stream['stream_source'])) {
            return ApiResponse::error('Not found', 404);
        }

        return $this->proxyFor($request, $line, $stream, (string) $stream['stream_source']);
    }

    public function movie(ApiRequest $request, string $username, string $password, string $file): ApiResponse
    {
        $line = $this->authenticate($username, $password);

        if ($line === null) {
            return ApiResponse::error('Not found', 404);
        }

        $stream = Db::first(
            "SELECT * FROM streams WHERE id = ? AND stream_type = 'movie' AND enabled = 1",
            [$this->fileId($file)]
        );

        if ($stream === null || empty($stream['stream_source'])) {
            return ApiResponse::error('Not found', 404);
        }

        return $this->proxyFor(
            $request,
            $line,
            $stream,
            (string) $stream['stream_source'],
            (string) $request->header('Range', ''),
            true
        );
    }

    public function series(ApiRequest $request, string $username, string $password, string $file): ApiResponse
    {
        $line = $this->authenticate($username, $password);

        if ($line === null) {
            return ApiResponse::error('Not found', 404);
        }

        $episode = Db::first(
            'SELECT * FROM episodes WHERE id = ? AND enabled = 1',
            [$this->fileId($file)]
        );

        if ($episode === null || empty($episode['source'])) {
            return ApiResponse::error('Not found', 404);
        }

        $series = Db::first('SELECT * FROM series WHERE id = ?', [(int) $episode['series_id']]) ?? [];

        return $this->proxyFor(
            $request,
            $line,
            $series + ['id' => (int) $episode['series_id']],
            (string) $episode['source'],
            (string) $request->header('Range', ''),
            true
        );
    }

    /**
     * Signed URL hit by the player after a playlist is rewritten.
     */
    public function stream(ApiRequest $request, string $token): ApiResponse
    {
        $payload = ProxyToken::parse($token);

        if ($payload === null) {
            return ApiResponse::error('Not found', 404);
        }

        $line = Db::first('SELECT * FROM user_lines WHERE id = ?', [(int) $payload['l']]);
        $stream = Db::first('SELECT * FROM streams WHERE id = ?', [(int) $payload['s']]);

        if ($line === null || !(bool) $line['enabled']) {
            return ApiResponse::error('Not found', 404);
        }

        // Series tokens reference a series id, not a streams row.
        $stream ??= ['id' => (int) $payload['s']];

        if ($line['exp_date'] !== null && $line['exp_date'] !== '' && (int) $line['exp_date'] <= time()) {
            return ApiResponse::error('Not found', 404);
        }

        $proxy = $this->makeProxy($request, $line, $stream, (string) ($payload['sid'] ?? ''));

        if (!$proxy->canConnect()) {
            return ApiResponse::error('Connection limit reached', 429);
        }

        if (($payload['k'] ?? '') === 'playlist') {
            return $proxy->servePlaylist((string) $payload['u']);
        }

        $durationHint = ($payload['k'] ?? '') === 'segment' ? 6 : 0;

        return $proxy->serveMedia(
            (string) $payload['u'],
            $this->contentTypeFor((string) $payload['u']),
            $durationHint
        );
    }

    private function proxyFor(
        ApiRequest $request,
        array $line,
        array $stream,
        string $source,
        ?string $range = null,
        bool $preflight = false
    ): ApiResponse {
        $proxy = $this->makeProxy($request, $line, $stream);

        if (!$proxy->canConnect()) {
            return ApiResponse::error('Connection limit reached', 429);
        }

        if ($proxy->isPlaylistUrl($source)) {
            return $proxy->servePlaylist($source);
        }

        $contentType = (($stream['stream_type'] ?? '') === 'live') ? 'video/mp2t' : $this->contentTypeFor($source);

        return $proxy->serveMedia(
            $source,
            $contentType,
            0,
            $range !== null && $range !== '' ? $range : null,
            $preflight || ($range !== null && $range !== '')
        );
    }

    private function contentTypeFor(string $url): string
    {
        $extension = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

        return match ($extension) {
            'ts' => 'video/mp2t',
            'm4s', 'mp4' => 'video/mp4',
            'aac' => 'audio/aac',
            'key' => 'application/octet-stream',
            default => 'application/octet-stream',
        };
    }

    private function makeProxy(ApiRequest $request, array $line, array $stream, string $sessionKey = ''): StreamProxy
    {
        return new StreamProxy(
            $line,
            $stream,
            $this->isHttps($request) ? 'https' : 'http',
            (string) $request->header('Host', 'localhost'),
            (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            (string) $request->header('User-Agent', ''),
            $sessionKey !== '' ? $sessionKey : null
        );
    }

    private function authenticate(string $username, string $password): ?array
    {
        if ($username === '' || $password === '') {
            return null;
        }

        $line = Db::first('SELECT * FROM user_lines WHERE username = ? LIMIT 1', [$username]);

        if ($line === null || !(bool) $line['enabled']) {
            return null;
        }

        if ($line['exp_date'] !== null && $line['exp_date'] !== '' && (int) $line['exp_date'] <= time()) {
            return null;
        }

        $stored = (string) ($line['password_hash'] ?? '');
        $valid = str_starts_with($stored, '$2y$') || str_starts_with($stored, '$argon2')
            ? password_verify($password, $stored)
            : hash_equals($stored, $password);

        return $valid ? $line : null;
    }

    private function loginPayload(ApiRequest $request, array $line, string $password): array
    {
        $host = $this->host($request);

        return [
            'user_info' => [
                'username' => $line['username'],
                'password' => $password,
                'message' => 'Welcome',
                'auth' => 1,
                'status' => 'Active',
                'exp_date' => $line['exp_date'] !== null ? (string) $line['exp_date'] : null,
                'is_trial' => '0',
                'active_cons' => '0',
                'created_at' => (string) $line['created_at'],
                'max_connections' => (string) $line['max_connections'],
                'allowed_output_formats' => ['m3u8', 'ts'],
            ],
            'server_info' => [
                'url' => $host,
                'port' => (string) ($this->isHttps($request) ? 443 : 80),
                'https_port' => '443',
                'server_protocol' => $this->isHttps($request) ? 'https' : 'http',
                'rtmp_port' => '1935',
                'timezone' => date_default_timezone_get(),
                'timestamp_now' => time(),
                'time_now' => date('Y-m-d H:i:s'),
            ],
        ];
    }

    private function categories(string $type): array
    {
        $rows = Db::select(
            'SELECT id, category_name FROM categories WHERE category_type = ? ORDER BY sort_order, category_name',
            [$type]
        );

        return array_map(static fn (array $row): array => [
            'category_id' => (string) $row['id'],
            'category_name' => $row['category_name'],
            'parent_id' => 0,
        ], $rows);
    }

    private function liveStreams(): array
    {
        return array_map(static fn (array $row): array => [
            'num' => (int) $row['id'],
            'name' => $row['stream_display_name'],
            'stream_type' => 'live',
            'stream_id' => (int) $row['id'],
            'stream_icon' => (string) ($row['stream_icon'] ?? ''),
            'epg_channel_id' => (string) ($row['epg_channel_id'] ?? ''),
            'added' => (string) ($row['created_at'] ?? ''),
            'category_id' => (string) ($row['category_id'] ?? ''),
            'custom_sid' => '',
            'tv_archive' => 0,
            'direct_source' => '',
            'tv_archive_duration' => 0,
        ], $this->liveRows());
    }

    private function vodStreams(): array
    {
        return array_map(static fn (array $row): array => [
            'num' => (int) $row['id'],
            'name' => $row['stream_display_name'],
            'stream_type' => 'movie',
            'stream_id' => (int) $row['id'],
            'stream_icon' => (string) ($row['stream_icon'] ?? ''),
            'rating' => (string) ($row['rating'] ?? '0'),
            'rating_5based' => round(((float) ($row['rating'] ?? 0)) / 2, 1),
            'added' => (string) ($row['created_at'] ?? ''),
            'category_id' => (string) ($row['category_id'] ?? ''),
            'container_extension' => pathinfo((string) $row['stream_source'], PATHINFO_EXTENSION) ?: 'mp4',
            'custom_sid' => '',
            'direct_source' => '',
        ], $this->vodRows());
    }

    private function vodInfo(int $id): array
    {
        $row = Db::first("SELECT * FROM streams WHERE id = ? AND stream_type = 'movie'", [$id]);

        if ($row === null) {
            return [];
        }

        return [
            'info' => [
                'movie_image' => (string) ($row['stream_icon'] ?? ''),
                'plot' => (string) ($row['overview'] ?? ''),
                'genre' => (string) ($row['genres'] ?? ''),
                'rating' => (string) ($row['rating'] ?? '0'),
                'duration' => isset($row['runtime']) ? $row['runtime'] . ' min' : '',
                'releasedate' => (string) ($row['year'] ?? ''),
                'backdrop_path' => [(string) ($row['backdrop'] ?? '')],
            ],
            'movie_data' => [
                'stream_id' => (int) $row['id'],
                'name' => $row['stream_display_name'],
                'added' => (string) $row['created_at'],
                'category_id' => (string) ($row['category_id'] ?? ''),
                'container_extension' => pathinfo((string) $row['stream_source'], PATHINFO_EXTENSION) ?: 'mp4',
                'custom_sid' => '',
                'direct_source' => '',
            ],
        ];
    }

    private function seriesList(): array
    {
        $rows = Db::select('SELECT * FROM series WHERE enabled = 1 ORDER BY name');

        return array_map(static fn (array $row): array => [
            'num' => (int) $row['id'],
            'name' => $row['name'],
            'series_id' => (int) $row['id'],
            'cover' => (string) ($row['cover'] ?? ''),
            'plot' => (string) ($row['overview'] ?? $row['description'] ?? ''),
            'cast' => '',
            'director' => '',
            'genre' => (string) ($row['genres'] ?? ''),
            'releaseDate' => (string) ($row['first_air_date'] ?? ''),
            'last_modified' => '',
            'rating' => (string) ($row['rating'] ?? '0'),
            'rating_5based' => round(((float) ($row['rating'] ?? 0)) / 2, 1),
            'backdrop_path' => [(string) ($row['backdrop'] ?? '')],
        ], $rows);
    }

    private function seriesInfo(int $id): array
    {
        $series = Db::first('SELECT * FROM series WHERE id = ?', [$id]);

        if ($series === null) {
            return [];
        }

        $episodes = [];

        foreach (Db::select('SELECT * FROM episodes WHERE series_id = ? ORDER BY season_num, episode_num', [$id]) as $episode) {
            $episodes[(string) $episode['season_num']][] = [
                'id' => (string) $episode['id'],
                'episode_num' => (int) $episode['episode_num'],
                'title' => $episode['title'],
                'container_extension' => pathinfo((string) $episode['source'], PATHINFO_EXTENSION) ?: 'mp4',
                'info' => [
                    'movie_image' => (string) ($series['cover'] ?? ''),
                    'plot' => (string) ($series['overview'] ?? ''),
                    'duration' => '',
                ],
            ];
        }

        $seasons = array_map(static fn (array $row): array => [
            'season_number' => (int) $row['season_num'],
            'name' => 'Season ' . $row['season_num'],
        ], Db::select('SELECT DISTINCT season_num FROM episodes WHERE series_id = ? ORDER BY season_num', [$id]));

        return [
            'seasons' => $seasons,
            'info' => [
                'name' => $series['name'],
                'cover' => (string) ($series['cover'] ?? ''),
                'plot' => (string) ($series['overview'] ?? ''),
                'cast' => '',
                'director' => '',
                'genre' => (string) ($series['genres'] ?? ''),
                'releaseDate' => (string) ($series['first_air_date'] ?? ''),
                'rating' => (string) ($series['rating'] ?? '0'),
                'backdrop_path' => [(string) ($series['backdrop'] ?? '')],
            ],
            'episodes' => $episodes,
        ];
    }

    private function liveRows(): array
    {
        return Db::select(
            "SELECT s.*, c.category_name FROM streams s
             LEFT JOIN categories c ON c.id = s.category_id
             WHERE s.stream_type = 'live' AND s.enabled = 1
             ORDER BY s.stream_display_name"
        );
    }

    private function vodRows(): array
    {
        return Db::select(
            "SELECT s.*, c.category_name FROM streams s
             LEFT JOIN categories c ON c.id = s.category_id
             WHERE s.stream_type = 'movie' AND s.enabled = 1
             ORDER BY s.stream_display_name"
        );
    }

    private function fileId(string $file): int
    {
        return (int) pathinfo($file, PATHINFO_FILENAME);
    }

    private function host(ApiRequest $request): string
    {
        $host = (string) $request->header('Host', 'localhost');

        return explode(':', $host)[0];
    }

    private function baseUrl(ApiRequest $request): string
    {
        $host = (string) $request->header('Host', 'localhost');

        return ($this->isHttps($request) ? 'https' : 'http') . '://' . $host;
    }

    private function isHttps(ApiRequest $request): bool
    {
        return strtolower((string) $request->header('X-Forwarded-Proto', '')) === 'https'
            || !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    }
}
