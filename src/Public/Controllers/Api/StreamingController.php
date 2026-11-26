<?php

declare(strict_types=1);

namespace ForkPanelXc\Public\Controllers\Api;

use ForkPanelXc\Core\Http\ApiRequest;
use ForkPanelXc\Core\Http\ApiResponse;
use ForkPanelXc\Core\Support\Db;
use ForkPanelXc\Core\Support\StreamToken;

class StreamingController extends BaseApiController
{
    public function live(ApiRequest $request, string $token): ApiResponse
    {
        return $this->redirectToSource($token);
    }

    public function movie(ApiRequest $request, string $token): ApiResponse
    {
        return $this->redirectToSource($token);
    }

    public function series(ApiRequest $request, string $token): ApiResponse
    {
        return $this->redirectToSource($token);
    }

    public function epgXml(ApiRequest $request, string $token): ApiResponse
    {
        $payload = StreamToken::parse($token);

        if ($payload === null) {
            return ApiResponse::json(['error' => 'Invalid or expired token'], 403);
        }

        $rows = Db::select(
            'SELECT p.*, c.name AS channel_name, c.epg_id FROM epg_programmes p
             JOIN epg_channels c ON c.id = p.channel_id
             WHERE p.end_time >= ? ORDER BY p.start_time ASC LIMIT 5000',
            [time()]
        );

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<tv generator-info-name="Fork Panel XC">' . "\n";

        foreach ($rows as $row) {
            $xml .= sprintf(
                "  <programme start=\"%s\" stop=\"%s\" channel=\"%s\">\n    <title>%s</title>\n  </programme>\n",
                date('YmdHis O', (int) $row['start_time']),
                date('YmdHis O', (int) $row['end_time']),
                htmlspecialchars((string) $row['epg_id'], ENT_XML1),
                htmlspecialchars((string) $row['title'], ENT_XML1)
            );
        }

        $xml .= '</tv>';

        return ApiResponse::raw($xml, 'application/xml; charset=utf-8');
    }

    private function redirectToSource(string $token): ApiResponse
    {
        $payload = StreamToken::parse($token);

        if ($payload === null) {
            return ApiResponse::json(['error' => 'Invalid or expired token'], 403);
        }

        $streamId = (int) ($payload['stream'] ?? 0);
        $stream = Db::first('SELECT stream_source FROM streams WHERE id = ? AND enabled = 1', [$streamId]);

        if ($stream === null) {
            return ApiResponse::json(['error' => 'Stream not found'], 404);
        }

        $source = (string) $stream['stream_source'];

        // Only http(s) sources are redirected; other schemes are refused to
        // avoid turning the endpoint into an open redirector.
        $scheme = strtolower((string) parse_url($source, PHP_URL_SCHEME));

        if (!in_array($scheme, ['http', 'https'], true)) {
            return ApiResponse::json(['error' => 'Unsupported stream scheme'], 422);
        }

        return ApiResponse::redirect($source);
    }
}
