<?php

declare(strict_types=1);

namespace ForkPanelXc\Public\Controllers\Api;

use ForkPanelXc\Core\Http\ApiRequest;
use ForkPanelXc\Core\Http\ApiResponse;
use ForkPanelXc\Core\Support\Db;

class StreamsController extends BaseApiController
{
    public function index(ApiRequest $request): ApiResponse
    {
        [$page, $perPage, $offset] = $this->pagination($request);
        [$field, $direction] = $this->sort($request, ['id', 'stream_display_name', 'stream_type', 'enabled', 'created_at']);

        $where = ['1 = 1'];
        $params = [];

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $where[] = 'stream_display_name LIKE ?';
            $params[] = '%' . $search . '%';
        }

        foreach (['status' => 'status', 'type' => 'stream_type', 'category_id' => 'category_id'] as $query => $column) {
            $value = $request->query($query);

            if ($value !== null && $value !== '') {
                $where[] = $column . ' = ?';
                $params[] = $value;
            }
        }

        $whereSql = implode(' AND ', $where);
        $total = (int) Db::column('SELECT COUNT(*) FROM streams WHERE ' . $whereSql, $params);

        $rows = Db::select(
            sprintf(
                'SELECT s.*, c.category_name FROM streams s LEFT JOIN categories c ON c.id = s.category_id
                 WHERE %s ORDER BY s.%s %s LIMIT %d OFFSET %d',
                $whereSql,
                $field,
                $direction,
                $perPage,
                $offset
            ),
            $params
        );

        return ApiResponse::json($this->paginated($rows, $total, $page, $perPage));
    }

    public function store(ApiRequest $request): ApiResponse
    {
        $data = $request->json();
        $errors = $this->require($data, ['stream_display_name', 'stream_source']);

        if ($errors !== null) {
            return ApiResponse::error('Validation failed', 422, $errors);
        }

        $id = Db::insert(
            'INSERT INTO streams (stream_display_name, stream_source, stream_icon, stream_type, category_id, enabled, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                (string) $data['stream_display_name'],
                (string) $data['stream_source'],
                (string) ($data['stream_icon'] ?? ''),
                (string) ($data['stream_type'] ?? 'live'),
                isset($data['category_id']) && $data['category_id'] !== '' ? (int) $data['category_id'] : null,
                !empty($data['enabled']) ? 1 : 0,
                time(),
                time(),
            ]
        );

        return ApiResponse::json(['data' => $this->find($id)], 201);
    }

    public function show(ApiRequest $request, string $id): ApiResponse
    {
        $stream = $this->find((int) $id);

        return $stream === null
            ? ApiResponse::error('Stream not found', 404)
            : ApiResponse::json(['data' => $stream]);
    }

    public function update(ApiRequest $request, string $id): ApiResponse
    {
        $streamId = (int) $id;

        if ($this->find($streamId) === null) {
            return ApiResponse::error('Stream not found', 404);
        }

        $data = $request->json();
        $allowed = ['stream_display_name', 'stream_source', 'stream_icon', 'stream_type', 'category_id', 'enabled', 'status'];

        $updates = array_intersect_key($data, array_flip($allowed));

        if ($updates === []) {
            return ApiResponse::json(['data' => $this->find($streamId)]);
        }

        $sets = implode(', ', array_map(static fn (string $key): string => $key . ' = ?', array_keys($updates)));
        $params = array_values($updates);
        $params[] = time();
        $params[] = $streamId;

        Db::execute(sprintf('UPDATE streams SET %s, updated_at = ? WHERE id = ?', $sets), $params);

        return ApiResponse::json(['data' => $this->find($streamId)]);
    }

    public function destroy(ApiRequest $request, string $id): ApiResponse
    {
        $deleted = Db::execute('DELETE FROM streams WHERE id = ?', [(int) $id]);

        return $deleted > 0
            ? ApiResponse::json(['success' => true])
            : ApiResponse::error('Stream not found', 404);
    }

    public function bulkDelete(ApiRequest $request): ApiResponse
    {
        $ids = $this->ids($request);

        if ($ids === []) {
            return ApiResponse::error('IDs array required', 422);
        }

        $deleted = Db::execute(
            'DELETE FROM streams WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')',
            $ids
        );

        return ApiResponse::json(['deleted' => $deleted]);
    }

    public function bulkUpdate(ApiRequest $request): ApiResponse
    {
        $ids = $this->ids($request);
        $updates = $request->json()['updates'] ?? null;

        if ($ids === [] || !is_array($updates) || $updates === []) {
            return ApiResponse::error('IDs array and updates object required', 422);
        }

        $allowed = array_intersect_key($updates, array_flip(['enabled', 'status', 'category_id']));

        if ($allowed === []) {
            return ApiResponse::error('No supported update fields', 422);
        }

        $sets = implode(', ', array_map(static fn (string $key): string => $key . ' = ?', array_keys($allowed)));
        $params = array_values($allowed);
        $params[] = time();
        $params = array_merge($params, $ids);

        $updated = Db::execute(
            sprintf('UPDATE streams SET %s, updated_at = ? WHERE id IN (%s)', $sets, implode(',', array_fill(0, count($ids), '?'))),
            $params
        );

        return ApiResponse::json(['updated' => $updated]);
    }

    public function categories(ApiRequest $request): ApiResponse
    {
        return ApiResponse::json(['data' => Db::select('SELECT id, category_name AS name, category_type AS type FROM categories ORDER BY category_name')]);
    }

    public function stats(ApiRequest $request, string $id): ApiResponse
    {
        $row = Db::first(
            'SELECT current_viewers, total_connections, bandwidth FROM stream_stats WHERE stream_id = ? LIMIT 1',
            [(int) $id]
        );

        return ApiResponse::json(['data' => $row ?? ['current_viewers' => 0, 'total_connections' => 0, 'bandwidth' => 0]]);
    }

    public function test(ApiRequest $request, string $id): ApiResponse
    {
        $stream = $this->find((int) $id);

        if ($stream === null) {
            return ApiResponse::error('Stream not found', 404);
        }

        $url = (string) $stream['stream_source'];
        $start = microtime(true);

        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'ForkPanelXC/1.0',
        ]);

        curl_exec($handle);
        $code = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $error = curl_error($handle);
        $contentType = (string) curl_getinfo($handle, CURLINFO_CONTENT_TYPE);
        curl_close($handle);

        return ApiResponse::json([
            'data' => [
                'success' => $error === '' && $code >= 200 && $code < 400,
                'latency' => (int) round((microtime(true) - $start) * 1000),
                'error' => $error !== '' ? $error : ($code >= 400 ? 'HTTP ' . $code : null),
                'info' => ['http_code' => $code, 'content_type' => $contentType],
            ],
        ]);
    }

    public function import(ApiRequest $request): ApiResponse
    {
        $file = $request->file('m3u_file');

        if ($file === null || ($file['error'] ?? 1) !== UPLOAD_ERR_OK) {
            return ApiResponse::error('M3U file required', 422);
        }

        $content = (string) file_get_contents($file['tmp_name']);
        $lines = preg_split('/\r\n|\n|\r/', $content) ?: [];
        $created = 0;
        $pending = null;

        foreach ($lines as $line) {
            $line = trim($line);

            if (str_starts_with($line, '#EXTINF:')) {
                $pending = $line;
                continue;
            }

            if ($pending !== null && $line !== '' && !str_starts_with($line, '#')) {
                $name = trim((string) substr($pending, (int) strrpos($pending, ',') + 1));

                Db::execute(
                    'INSERT INTO streams (stream_display_name, stream_source, stream_type, enabled, created_at, updated_at)
                     VALUES (?, ?, ?, 1, ?, ?)',
                    [$name !== '' ? $name : 'Imported stream', $line, 'live', time(), time()]
                );

                $created++;
                $pending = null;
            }
        }

        return ApiResponse::json(['data' => ['created' => $created]]);
    }

    public function export(ApiRequest $request): ApiResponse
    {
        $rows = Db::select('SELECT stream_display_name, stream_source FROM streams ORDER BY id');
        $out = ["#EXTM3U"];

        foreach ($rows as $row) {
            $out[] = '#EXTINF:-1,' . $row['stream_display_name'];
            $out[] = (string) $row['stream_source'];
        }

        return ApiResponse::download(implode("\n", $out), 'streams_' . date('Y-m-d') . '.m3u', 'audio/x-mpegurl');
    }

    private function find(int $id): ?array
    {
        return Db::first(
            'SELECT s.*, c.category_name FROM streams s LEFT JOIN categories c ON c.id = s.category_id WHERE s.id = ?',
            [$id]
        );
    }

    private function ids(ApiRequest $request): array
    {
        $ids = $request->json()['ids'] ?? [];

        if (!is_array($ids)) {
            return [];
        }

        return array_values(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0));
    }
}
