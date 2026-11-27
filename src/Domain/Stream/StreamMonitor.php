<?php

declare(strict_types=1);

namespace ForkPanelXc\Domain\Stream;

use ForkPanelXc\Core\Support\Db;

/**
 * Periodically probes enabled streams and keeps streams.status / monitor_*
 * fields up to date. Runs from the scheduler every minute.
 */
final class StreamMonitor
{
    public static function runDue(int $limit = 25): array
    {
        $now = time();
        $streams = Db::select(
            'SELECT * FROM streams
             WHERE enabled = 1 AND monitor_enabled = 1
               AND (monitor_last_check IS NULL OR monitor_last_check + monitor_interval <= ?)
             ORDER BY monitor_last_check IS NULL DESC, monitor_last_check ASC
             LIMIT ' . max(1, $limit),
            [$now]
        );

        return self::checkMany($streams);
    }

    public static function checkAll(int $limit = 100): array
    {
        $streams = Db::select(
            'SELECT * FROM streams WHERE enabled = 1 ORDER BY monitor_last_check IS NULL DESC, monitor_last_check ASC LIMIT ' . max(1, $limit)
        );

        return self::checkMany($streams);
    }

    private static function checkMany(array $streams): array
    {
        $checked = 0;
        $online = 0;

        foreach ($streams as $stream) {
            $result = self::check($stream);
            $checked++;

            if ($result['online']) {
                $online++;
            }
        }

        return ['checked' => $checked, 'online' => $online, 'offline' => $checked - $online];
    }

    public static function checkNow(int $streamId): ?array
    {
        $stream = Db::first('SELECT * FROM streams WHERE id = ?', [$streamId]);

        return $stream === null ? null : self::check($stream);
    }

    private static function check(array $stream): array
    {
        $source = (string) ($stream['stream_source'] ?? '');
        $scheme = strtolower((string) parse_url($source, PHP_URL_SCHEME));
        $started = microtime(true);
        $code = 0;
        $error = '';
        $contentType = '';

        if ($source === '' || !in_array($scheme, ['http', 'https'], true)) {
            $error = 'Unsupported source';
        } else {
            [$code, $error, $contentType] = self::probe($source);

            // Some origins reject HEAD; retry a tiny ranged GET.
            if ($code === 405 || $code === 501) {
                [$code, $error, $contentType] = self::probe($source, false);
            }
        }

        $online = $error === '' && $code >= 200 && $code < 400;
        $latency = (int) round((microtime(true) - $started) * 1000);
        $now = time();

        $threshold = max(1, (int) ($stream['monitor_fail_threshold'] ?? 1));
        $previous = $stream['monitor_last_status'];
        $previousOnline = $previous !== null && (int) $previous === 1;
        $failCount = $online ? 0 : min($threshold, (int) ($stream['monitor_fail_count'] ?? 0) + 1);

        // Stay online during the grace window; go offline once the threshold is
        // reached. A stream that was never online is offline immediately.
        $effectiveOnline = $online || ($failCount < $threshold && $previousOnline);
        $status = $effectiveOnline ? 'online' : 'offline';

        Db::execute(
            'UPDATE streams SET status = ?, monitor_last_check = ?, monitor_last_status = ?,
                    monitor_last_error = ?, monitor_fail_count = ?, updated_at = ? WHERE id = ?',
            [
                $status,
                $now,
                $effectiveOnline ? 1 : 0,
                $error !== '' ? mb_substr($error, 0, 500) : null,
                $failCount,
                $now,
                (int) $stream['id'],
            ]
        );

        $shouldLog = $previous === null || $previousOnline !== $effectiveOnline;

        if ($shouldLog) {
            Db::execute(
                'INSERT INTO stream_logs (stream_id, level, message, context, created_at) VALUES (?, ?, ?, ?, ?)',
                [
                    (int) $stream['id'],
                    $effectiveOnline ? 'info' : 'warning',
                    $effectiveOnline ? 'Monitor: online' : 'Monitor: offline',
                    json_encode([
                        'http_code' => $code,
                        'latency_ms' => $latency,
                        'content_type' => $contentType,
                        'error' => $error !== '' ? $error : null,
                        'fail_count' => $failCount,
                        'fail_threshold' => $threshold,
                    ]),
                    $now,
                ]
            );
        }

        // XUI-style auto-restart: when a monitored stream crosses to offline and
        // the option is enabled, drop its sessions so players reconnect.
        if (!$effectiveOnline && $previousOnline && (int) ($stream['monitor_autorestart'] ?? 0) === 1) {
            Db::execute('DELETE FROM stream_sessions WHERE stream_id = ?', [(int) $stream['id']]);
            Db::execute(
                'INSERT INTO stream_logs (stream_id, level, message, context, created_at) VALUES (?, ?, ?, ?, ?)',
                [
                    (int) $stream['id'],
                    'warning',
                    'Monitor: auto-restart',
                    json_encode(['reason' => 'source offline', 'fail_count' => $failCount]),
                    $now,
                ]
            );
        }

        return [
            'online' => $effectiveOnline,
            'http_code' => $code,
            'latency' => $latency,
            'content_type' => $contentType,
            'error' => $error !== '' ? $error : null,
            'fail_count' => $failCount,
            'fail_threshold' => $threshold,
        ];
    }

    private static function probe(string $url, bool $head = true): array
    {
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY => $head,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_USERAGENT => 'ForkPanelXC-Monitor/1.0',
        ]);

        if (!$head) {
            curl_setopt($handle, CURLOPT_RANGE, '0-0');
        }

        curl_exec($handle);
        $code = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $error = (string) curl_error($handle);
        $contentType = (string) curl_getinfo($handle, CURLINFO_CONTENT_TYPE);
        curl_close($handle);

        return [$code, $error, $contentType];
    }
}
