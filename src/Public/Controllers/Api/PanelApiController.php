<?php

declare(strict_types=1);

namespace ForkPanelXc\Public\Controllers\Api;

use ForkPanelXc\Core\Http\ApiRequest;
use ForkPanelXc\Core\Http\ApiResponse;
use ForkPanelXc\Core\Support\Db;

/**
 * Minimal Stalker/Ministra-compatible panel API used by MAG devices.
 */
class PanelApiController extends BaseApiController
{
    public function login(ApiRequest $request): ApiResponse
    {
        $mac = strtoupper((string) ($request->post('mac') ?? $request->query('mac', '')));

        $device = $mac === '' ? null : Db::first('SELECT * FROM mag_devices WHERE mac = ? LIMIT 1', [$mac]);

        if ($device === null) {
            return ApiResponse::json(['error' => 'Device not authorised'], 401);
        }

        return ApiResponse::json([
            'token' => rtrim(strtr(base64_encode(hash('sha256', $mac . time())), '+/', '-_'), '='),
            'random' => (string) random_int(100000, 999999),
        ]);
    }

    public function getChannels(ApiRequest $request): ApiResponse
    {
        $rows = Db::select("SELECT id, stream_display_name, stream_icon, stream_source FROM streams WHERE stream_type = 'live' AND enabled = 1 ORDER BY stream_display_name");

        return ApiResponse::json([
            'js' => array_map(static fn (array $row): array => [
                'id' => (string) $row['id'],
                'name' => $row['stream_display_name'],
                'logo' => (string) ($row['stream_icon'] ?? ''),
                'cmd' => (string) $row['stream_source'],
            ], $rows),
        ]);
    }

    public function getEpg(ApiRequest $request): ApiResponse
    {
        return ApiResponse::json(['js' => []]);
    }
}
