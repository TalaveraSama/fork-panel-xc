<?php

declare(strict_types=1);

namespace ForkPanelXc\Public\Controllers\Api;

use ForkPanelXc\Core\Http\ApiRequest;
use ForkPanelXc\Core\Http\ApiResponse;
use ForkPanelXc\Core\Support\Db;

class DashboardController extends BaseApiController
{
    public function stats(ApiRequest $request): ApiResponse
    {
        \ForkPanelXc\Domain\Stream\StreamStats::reconcile();

        $totalStreams = (int) Db::column('SELECT COUNT(*) FROM streams');
        $onlineStreams = (int) Db::column("SELECT COUNT(*) FROM streams WHERE status = 'online'");
        $totalUsers = (int) Db::column('SELECT COUNT(*) FROM users');
        $activeUsers = (int) Db::column('SELECT COUNT(*) FROM users WHERE enabled = 1');
        $connections = (int) Db::column('SELECT COUNT(*) FROM user_lines WHERE connected = 1');

        $byType = [];

        foreach (['live', 'vod', 'series', 'radio'] as $type) {
            $byType[$type] = (int) Db::column('SELECT COUNT(*) FROM streams WHERE stream_type = ?', [$type]);
        }

        $system = $this->systemMetrics();
        $history = $this->history();

        return ApiResponse::json([
            'totalStreams' => $totalStreams,
            'onlineStreams' => $onlineStreams,
            'totalUsers' => $totalUsers,
            'activeUsers' => $activeUsers,
            'totalConnections' => $connections,
            'bandwidth' => $system['bandwidth'],
            'cpu' => $system['cpu'],
            'memory' => $system['memory'],
            'disk' => $system['disk'],
            'streamsByType' => $byType,
            'history' => $history,
        ]);
    }

    public function activity(ApiRequest $request): ApiResponse
    {
        $limit = min(50, max(1, (int) $request->query('limit', 10)));

        $rows = Db::select(
            'SELECT id, event, status, user, created_at FROM activity_log ORDER BY id DESC LIMIT ' . $limit
        );

        $data = array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'event' => (string) $row['event'],
            'status' => (string) $row['status'],
            'user' => (string) ($row['user'] ?? 'System'),
            'timestamp' => (int) $row['created_at'],
        ], $rows);

        return ApiResponse::json(['data' => $data]);
    }

    private function systemMetrics(): array
    {
        $load = function_exists('sys_getloadavg') ? (sys_getloadavg() ?: [0, 0, 0]) : [0, 0, 0];
        $cores = 1;

        if (is_readable('/proc/cpuinfo')) {
            $count = preg_match_all('/^processor\s*:/m', (string) file_get_contents('/proc/cpuinfo'));
            $cores = $count > 0 ? $count : 1;
        }

        $memTotal = 0;
        $memAvailable = 0;

        if (is_readable('/proc/meminfo')) {
            $meminfo = (string) file_get_contents('/proc/meminfo');
            if (preg_match('/MemTotal:\s+(\d+)/', $meminfo, $m)) {
                $memTotal = (int) $m[1];
            }
            if (preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $m)) {
                $memAvailable = (int) $m[1];
            }
        }

        $memory = $memTotal > 0 ? (int) round((1 - $memAvailable / $memTotal) * 100) : 0;
        $disk = 0;
        $total = @disk_total_space('/');
        $free = @disk_free_space('/');

        if ($total !== false && $free !== false && $total > 0) {
            $disk = (int) round((1 - $free / $total) * 100);
        }

        $connections = (int) Db::column('SELECT COUNT(*) FROM user_lines WHERE connected = 1');

        return [
            'cpu' => (int) min(100, round(($load[0] / $cores) * 100)),
            'memory' => $memory,
            'disk' => $disk,
            'bandwidth' => (float) $this->currentBandwidth($connections),
        ];
    }

    private function currentBandwidth(int $connections): float
    {
        $row = Db::first('SELECT SUM(bitrate) AS total FROM user_lines WHERE connected = 1');

        return round(((float) ($row['total'] ?? 0)) / 1000, 2);
    }

    private function history(): array
    {
        $labels = [];
        $inbound = [];
        $outbound = [];
        $connections = [];

        $rows = Db::select(
            'SELECT hour_label, inbound, outbound, connections FROM stats_hourly ORDER BY id DESC LIMIT 24'
        );

        foreach (array_reverse($rows) as $row) {
            $labels[] = (string) $row['hour_label'];
            $inbound[] = (float) $row['inbound'];
            $outbound[] = (float) $row['outbound'];
            $connections[] = (int) $row['connections'];
        }

        return [
            'labels' => $labels,
            'inbound' => $inbound,
            'outbound' => $outbound,
            'connections' => $connections,
        ];
    }
}
