<?php

declare(strict_types=1);

namespace ForkPanelXc\Public\Controllers\Api;

use ForkPanelXc\Core\Http\ApiRequest;
use ForkPanelXc\Core\Http\ApiResponse;
use ForkPanelXc\Core\Support\Db;

class BouquetsController extends BaseApiController
{
    public function index(ApiRequest $request): ApiResponse
    {
        [$page, $perPage, $offset] = $this->pagination($request);

        $total = (int) Db::column('SELECT COUNT(*) FROM bouquets');
        $rows = Db::select(
            sprintf(
                'SELECT b.*, (SELECT COUNT(*) FROM bouquet_streams bs WHERE bs.bouquet_id = b.id) AS streams_count
                 FROM bouquets b ORDER BY b.id DESC LIMIT %d OFFSET %d',
                $perPage,
                $offset
            )
        );

        return ApiResponse::json($this->paginated($rows, $total, $page, $perPage));
    }

    public function store(ApiRequest $request): ApiResponse
    {
        $data = $request->json();

        if (empty($data['name'])) {
            return ApiResponse::error('Name is required', 422, ['name' => ['Name is required']]);
        }

        $id = Db::insert(
            'INSERT INTO bouquets (name, description, enabled, created_at, updated_at) VALUES (?, ?, 1, ?, ?)',
            [(string) $data['name'], (string) ($data['description'] ?? ''), time(), time()]
        );

        return ApiResponse::json(['data' => $this->find($id)], 201);
    }

    public function show(ApiRequest $request, string $id): ApiResponse
    {
        $bouquet = $this->find((int) $id);

        if ($bouquet === null) {
            return ApiResponse::error('Bouquet not found', 404);
        }

        $bouquet['streams'] = Db::select(
            'SELECT s.id, s.stream_display_name AS name, s.stream_type FROM bouquet_streams bs
             JOIN streams s ON s.id = bs.stream_id WHERE bs.bouquet_id = ? ORDER BY s.stream_display_name',
            [(int) $id]
        );

        return ApiResponse::json(['data' => $bouquet]);
    }

    public function update(ApiRequest $request, string $id): ApiResponse
    {
        $data = $request->json();
        $allowed = array_intersect_key($data, array_flip(['name', 'description', 'enabled']));

        if ($allowed === []) {
            return ApiResponse::error('No supported fields', 422);
        }

        $sets = implode(', ', array_map(static fn (string $key): string => $key . ' = ?', array_keys($allowed)));
        $params = array_values($allowed);
        $params[] = time();
        $params[] = (int) $id;

        Db::execute(sprintf('UPDATE bouquets SET %s, updated_at = ? WHERE id = ?', $sets), $params);

        return ApiResponse::json(['data' => $this->find((int) $id)]);
    }

    public function destroy(ApiRequest $request, string $id): ApiResponse
    {
        Db::execute('DELETE FROM bouquet_streams WHERE bouquet_id = ?', [(int) $id]);
        $deleted = Db::execute('DELETE FROM bouquets WHERE id = ?', [(int) $id]);

        return $deleted > 0 ? ApiResponse::json(['success' => true]) : ApiResponse::error('Bouquet not found', 404);
    }

    public function addStream(ApiRequest $request, string $id): ApiResponse
    {
        $streamId = (int) ($request->json()['stream_id'] ?? 0);

        if ($streamId <= 0) {
            return ApiResponse::error('stream_id is required', 422);
        }

        Db::execute('INSERT IGNORE INTO bouquet_streams (bouquet_id, stream_id) VALUES (?, ?)', [(int) $id, $streamId]);

        return ApiResponse::json(['success' => true]);
    }

    public function removeStream(ApiRequest $request, string $id, string $streamId): ApiResponse
    {
        Db::execute('DELETE FROM bouquet_streams WHERE bouquet_id = ? AND stream_id = ?', [(int) $id, (int) $streamId]);

        return ApiResponse::json(['success' => true]);
    }

    private function find(int $id): ?array
    {
        return Db::first(
            'SELECT b.*, (SELECT COUNT(*) FROM bouquet_streams bs WHERE bs.bouquet_id = b.id) AS streams_count
             FROM bouquets b WHERE b.id = ?',
            [$id]
        );
    }
}
