<?php

declare(strict_types=1);

namespace ForkPanelXc\Domain\Stream;

use ForkPanelXc\Core\Http\ApiResponse;
use ForkPanelXc\Core\Support\Db;
use ForkPanelXc\Core\Support\ProxyToken;

/**
 * Small HLS/TS gateway used by the Xtream compatibility endpoints.
 *
 * Instead of redirecting the player to the origin (which makes the panel
 * invisible to stats), the gateway fetches playlists, rewrites their URIs to
 * signed /stream/{token} URLs and proxies media bytes. Every request touches a
 * stream_sessions row so viewers/connections/bandwidth can be computed.
 */
final class StreamProxy
{
    private string $sessionKey;

    public function __construct(
        private array $line,
        private array $stream,
        private string $scheme,
        private string $host,
        private string $ip,
        private string $userAgent,
        ?string $sessionKey = null
    ) {
        $this->sessionKey = $sessionKey ?: substr(sha1(implode('|', [
            (int) $line['id'],
            (int) $stream['id'],
            $this->ip,
            $this->userAgent,
        ])), 0, 32);
    }

    public function servePlaylist(string $url): ApiResponse
    {
        [$body, $status, $contentType] = $this->fetch($url, 20);

        if ($status < 200 || $status >= 300 || $body === '') {
            return ApiResponse::error('Source playlist unavailable', 502);
        }

        $rewritten = $this->rewritePlaylist($body, $url);
        $this->touchSession(0, 0);

        return ApiResponse::raw(
            $rewritten,
            'application/vnd.apple.mpegurl; charset=utf-8'
        );
    }

    public function serveMedia(string $url, string $contentType = 'application/octet-stream', int $durationHint = 0): ApiResponse
    {
        $this->touchSession(0, 0);

        return ApiResponse::stream(
            function () use ($url, $contentType, $durationHint): void {
                $this->streamMedia($url, $contentType, $durationHint);
            },
            ['Content-Type' => $contentType]
        );
    }

    public function isPlaylistUrl(string $url): bool
    {
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        return str_ends_with($path, '.m3u8');
    }

    /**
     * Enforce user_lines.max_connections for new sessions. Existing sessions
     * (playlist/segment refreshes) are always allowed.
     */
    public function canConnect(): bool
    {
        $now = time();
        $activeSince = $now - 45;

        $current = Db::first(
            'SELECT last_seen, revoked, revoked_at FROM stream_sessions WHERE session_key = ?',
            [$this->sessionKey]
        );

        if ($current !== null && (int) $current['revoked'] === 1) {
            if ((int) $current['revoked_at'] > $now - 60) {
                return false;
            }
        }

        $max = (int) ($this->line['max_connections'] ?? 0);

        if ($max <= 0) {
            return true;
        }

        if ($current !== null && (int) $current['last_seen'] >= $activeSince && (int) $current['revoked'] === 0) {
            return true;
        }

        $active = (int) Db::column(
            'SELECT COUNT(DISTINCT session_key) FROM stream_sessions
             WHERE line_id = ? AND last_seen >= ? AND revoked = 0 AND revoked = 0',
            [(int) $this->line['id'], $activeSince]
        );

        return $active < $max;
    }

    private function streamMedia(string $url, string $contentType, int $durationHint = 0): void
    {
        @set_time_limit(0);
        while (ob_get_level() > 0) {
            @ob_end_flush();
        }
        @ob_implicit_flush(true);

        $written = 0;
        $pending = 0;
        $status = 0;
        $started = microtime(true);
        $lastTouch = $started;

        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_USERAGENT => $this->userAgent ?: 'ForkPanelXC/1.0',
            CURLOPT_HTTPHEADER => ['Accept: */*'],
            CURLOPT_HEADERFUNCTION => function ($curl, string $header) use (&$status): int {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $match)) {
                    $status = (int) $match[1];
                }

                return strlen($header);
            },
            CURLOPT_WRITEFUNCTION => function ($curl, string $chunk) use (&$written, &$pending, &$status, &$lastTouch): int {
                if ($status >= 400) {
                    return strlen($chunk);
                }

                echo $chunk;
                $length = strlen($chunk);
                $written += $length;
                $pending += $length;
                flush();

                // Long direct TS/MP4 sessions: publish bytes every 2 s so the
                // viewer appears in stats while the stream is still running.
                if ($pending >= 262144 && (microtime(true) - $lastTouch) >= 2) {
                    $bitrate = (int) round(($pending * 8) / 2 / 1000);
                    $this->touchSession($pending, $bitrate);
                    $pending = 0;
                    $lastTouch = microtime(true);
                }

                return $length;
            },
        ]);

        curl_exec($handle);
        $error = curl_error($handle);
        curl_close($handle);

        if ($pending > 0) {
            $seconds = $durationHint > 0 ? $durationHint : max(1, (int) round(microtime(true) - $started));
            $bitrate = (int) round(($pending * 8) / $seconds / 1000);
            $this->touchSession($pending, $bitrate);
        } elseif ($written === 0 && $error !== '') {
            error_log('[stream-gateway] ' . $error);
        }
    }

    private function touchSession(int $bytes = 0, int $bitrateKbps = 0): void
    {
        $now = time();

        try {
            $existing = Db::first('SELECT id FROM stream_sessions WHERE session_key = ?', [$this->sessionKey]);

            if ($existing === null) {
                Db::execute(
                    'INSERT INTO stream_sessions (session_key, line_id, stream_id, ip, user_agent, bytes, bitrate, started_at, last_seen, revoked, revoked_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NULL)',
                    [
                        $this->sessionKey,
                        (int) $this->line['id'],
                        (int) $this->stream['id'],
                        $this->ip,
                        mb_substr($this->userAgent, 0, 255),
                        $bytes,
                        $bitrateKbps,
                        $now,
                        $now,
                    ]
                );
            } else {
                if ($bitrateKbps > 0) {
                    Db::execute(
                        'UPDATE stream_sessions SET bytes = bytes + ?, bitrate = ?, last_seen = ?, revoked = 0, revoked_at = NULL WHERE id = ?',
                        [$bytes, $bitrateKbps, $now, (int) $existing['id']]
                    );
                } else {
                    Db::execute(
                        'UPDATE stream_sessions SET bytes = bytes + ?, last_seen = ?, revoked = 0, revoked_at = NULL WHERE id = ?',
                        [$bytes, $now, (int) $existing['id']]
                    );
                }
            }

            $this->cleanupSessions();
            $this->refreshStats();
        } catch (\Throwable $e) {
            error_log('[stream-gateway] session update failed: ' . $e->getMessage());
        }
    }

    private function cleanupSessions(): void
    {
        Db::execute('DELETE FROM stream_sessions WHERE last_seen < ?', [time() - 600]);
    }

    private function refreshStats(): void
    {
        $activeSince = time() - 45;
        $streamId = (int) $this->stream['id'];
        $lineId = (int) $this->line['id'];

        $viewers = (int) Db::column(
            'SELECT COUNT(*) FROM stream_sessions WHERE stream_id = ? AND last_seen >= ? AND revoked = 0',
            [$streamId, $activeSince]
        );
        $bandwidth = (int) Db::column(
            'SELECT COALESCE(SUM(bitrate), 0) FROM stream_sessions WHERE stream_id = ? AND last_seen >= ? AND revoked = 0',
            [$streamId, $activeSince]
        );

        Db::execute(
            'INSERT INTO stream_stats (stream_id, current_viewers, total_connections, bandwidth) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE current_viewers = VALUES(current_viewers),
                total_connections = VALUES(total_connections), bandwidth = VALUES(bandwidth)',
            [$streamId, $viewers, $viewers, $bandwidth]
        );

        Db::execute(
            'UPDATE streams SET status = ? WHERE id = ?',
            [$viewers > 0 ? 'online' : 'offline', $streamId]
        );

        $lineViewers = (int) Db::column(
            'SELECT COUNT(*) FROM stream_sessions WHERE line_id = ? AND last_seen >= ? AND revoked = 0',
            [$lineId, $activeSince]
        );
        $lineBandwidth = (int) Db::column(
            'SELECT COALESCE(SUM(bitrate), 0) FROM stream_sessions WHERE line_id = ? AND last_seen >= ? AND revoked = 0',
            [$lineId, $activeSince]
        );

        Db::execute(
            'UPDATE user_lines SET connected = ?, bitrate = ? WHERE id = ?',
            [$lineViewers > 0 ? 1 : 0, $lineBandwidth, $lineId]
        );
    }

    private function rewritePlaylist(string $body, string $playlistUrl): string
    {
        $output = [];
        $isMaster = str_contains($body, '#EXT-X-STREAM-INF');
        $tagKind = $isMaster ? 'playlist' : 'segment';
        $uriKind = $isMaster ? 'playlist' : 'segment';

        foreach (preg_split('/\r\n|\n|\r/', $body) ?: [] as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                $output[] = $line;
                continue;
            }

            if ($trimmed[0] === '#') {
                $output[] = preg_replace_callback(
                    '/(URI|url)="([^"]+)"/i',
                    fn (array $match): string => sprintf('%s="%s"', $match[1], $this->proxyUri($playlistUrl, $match[2], $tagKind)),
                    $line
                ) ?? $line;

                continue;
            }

            $output[] = $this->proxyUri($playlistUrl, $trimmed, $uriKind);
        }

        return implode("\n", $output);
    }

    private function proxyUri(string $base, string $uri, string $kind): string
    {
        if ($uri === '' || preg_match('#^(data|blob|skd):#i', $uri)) {
            return $uri;
        }

        $absolute = $this->resolveUrl($base, $uri);
        $scheme = strtolower((string) parse_url($absolute, PHP_URL_SCHEME));

        if (!in_array($scheme, ['http', 'https'], true)) {
            return $uri;
        }

        $token = ProxyToken::issue([
            'l' => (int) $this->line['id'],
            's' => (int) $this->stream['id'],
            'u' => $absolute,
            'k' => $kind,
            'sid' => $this->sessionKey,
        ]);

        return $this->scheme . '://' . $this->host . '/stream/' . $token;
    }

    private function resolveUrl(string $base, string $uri): string
    {
        if (preg_match('#^https?://#i', $uri)) {
            return $uri;
        }

        if (str_starts_with($uri, '//')) {
            return parse_url($base, PHP_URL_SCHEME) . ':' . $uri;
        }

        $parts = parse_url($base);
        $scheme = $parts['scheme'] ?? 'http';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = $parts['path'] ?? '/';

        if (str_starts_with($uri, '/')) {
            return $scheme . '://' . $host . $port . $uri;
        }

        $directory = rtrim(str_replace('\\', '/', dirname($path)), '/');
        $resolved = $directory . '/' . $uri;

        // Normalize ./ and ../ segments.
        $segments = [];
        foreach (explode('/', $resolved) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);
                continue;
            }

            $segments[] = $segment;
        }

        return $scheme . '://' . $host . $port . '/' . implode('/', $segments);
    }

    private function fetch(string $url, int $timeout): array
    {
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_USERAGENT => $this->userAgent ?: 'ForkPanelXC/1.0',
            CURLOPT_HTTPHEADER => ['Accept: */*'],
        ]);

        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $contentType = (string) curl_getinfo($handle, CURLINFO_CONTENT_TYPE);
        $error = curl_error($handle);
        curl_close($handle);

        if ($body === false || $error !== '') {
            return ['', 0, ''];
        }

        return [(string) $body, $status, $contentType];
    }
}
