<?php

declare(strict_types=1);

namespace ForkPanelXc\Domain\Stream;

use ForkPanelXc\Core\Support\Db;

/**
 * Reconciles stream_stats / streams.status / user_lines.connected with the
 * active rows in stream_sessions. Called lazily by read endpoints so stats
 * expire even when no player is currently requesting segments.
 */
final class StreamStats
{
    private static bool $reconciled = false;

    public static function reconcile(bool $force = false): void
    {
        if (self::$reconciled && !$force) {
            return;
        }

        self::$reconciled = true;

        try {
            $activeSince = time() - 45;
            $activeStreams = Db::select(
                'SELECT stream_id, COUNT(*) AS viewers, COALESCE(SUM(bitrate), 0) AS bandwidth
                 FROM stream_sessions WHERE last_seen >= ? AND revoked = 0 GROUP BY stream_id',
                [$activeSince]
            );

            Db::execute("UPDATE streams SET status = 'offline' WHERE status = 'online'");

            foreach ($activeStreams as $row) {
                Db::execute('UPDATE streams SET status = ? WHERE id = ?', ['online', (int) $row['stream_id']]);
                Db::execute(
                    'INSERT INTO stream_stats (stream_id, current_viewers, total_connections, bandwidth) VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE current_viewers = VALUES(current_viewers),
                        total_connections = VALUES(total_connections), bandwidth = VALUES(bandwidth)',
                    [
                        (int) $row['stream_id'],
                        (int) $row['viewers'],
                        (int) $row['viewers'],
                        (int) $row['bandwidth'],
                    ]
                );
            }

            if ($activeStreams === []) {
                Db::execute('UPDATE stream_stats SET current_viewers = 0, total_connections = 0, bandwidth = 0');
            } else {
                $ids = array_map(static fn (array $row): int => (int) $row['stream_id'], $activeStreams);
                Db::execute(
                    'UPDATE stream_stats SET current_viewers = 0, total_connections = 0, bandwidth = 0
                     WHERE stream_id NOT IN (' . implode(',', array_fill(0, count($ids), '?')) . ')',
                    $ids
                );
            }

            Db::execute('UPDATE user_lines SET connected = 0, bitrate = 0');
            $activeLines = Db::select(
                'SELECT line_id, COUNT(*) AS viewers, COALESCE(SUM(bitrate), 0) AS bandwidth
                 FROM stream_sessions WHERE last_seen >= ? AND revoked = 0 GROUP BY line_id',
                [$activeSince]
            );

            foreach ($activeLines as $row) {
                Db::execute(
                    'UPDATE user_lines SET connected = ?, bitrate = ? WHERE id = ?',
                    [(int) $row['viewers'] > 0 ? 1 : 0, (int) $row['bandwidth'], (int) $row['line_id']]
                );
            }

            Db::execute('DELETE FROM stream_sessions WHERE last_seen < ?', [time() - 600]);
        } catch (\Throwable $e) {
            error_log('[stream-stats] reconcile failed: ' . $e->getMessage());
        }
    }
}
