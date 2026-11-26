<?php

declare(strict_types=1);

namespace ForkPanelXc\Public\Controllers\Api;

use ForkPanelXc\Core\Http\ApiRequest;
use ForkPanelXc\Core\Http\ApiResponse;
use ForkPanelXc\Core\Support\Db;

class SeriesController extends BaseApiController
{
    public function index(ApiRequest $request): ApiResponse
    {
        [$page, $perPage, $offset] = $this->pagination($request);

        $where = ['1 = 1'];
        $params = [];

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $where[] = 'name LIKE ?';
            $params[] = '%' . $search . '%';
        }

        $whereSql = implode(' AND ', $where);
        $total = (int) Db::column('SELECT COUNT(*) FROM series WHERE ' . $whereSql, $params);
        $rows = Db::select(
            sprintf(
                'SELECT s.*,
                        (SELECT COUNT(DISTINCT season_num) FROM episodes WHERE series_id = s.id) AS seasons_count,
                        (SELECT COUNT(*) FROM episodes WHERE series_id = s.id) AS episodes_count
                 FROM series s WHERE %s ORDER BY name ASC LIMIT %d OFFSET %d',
                $whereSql,
                $perPage,
                $offset
            ),
            $params
        );

        return ApiResponse::json($this->paginated($rows, $total, $page, $perPage));
    }

    public function show(ApiRequest $request, string $id): ApiResponse
    {
        $row = Db::first('SELECT * FROM series WHERE id = ?', [(int) $id]);

        if ($row === null) {
            return ApiResponse::error('Series not found', 404);
        }

        $row['episodes'] = Db::select('SELECT * FROM episodes WHERE series_id = ? ORDER BY season_num, episode_num', [(int) $id]);

        return ApiResponse::json(['data' => $row]);
    }

    public function store(ApiRequest $request): ApiResponse
    {
        $data = $request->json();

        if (empty($data['name']) && empty($data['title'])) {
            return ApiResponse::error('Title is required', 422, ['name' => ['Title is required']]);
        }

        $id = Db::insert(
            'INSERT INTO series (name, description, cover, enabled, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
            [
                (string) ($data['name'] ?? $data['title']),
                (string) ($data['description'] ?? ''),
                (string) ($data['cover'] ?? ''),
                !empty($data['enabled']) ? 1 : 0,
                time(),
                time(),
            ]
        );

        return ApiResponse::json(['data' => Db::first('SELECT * FROM series WHERE id = ?', [$id])], 201);
    }

    public function update(ApiRequest $request, string $id): ApiResponse
    {
        $data = $request->json();
        $allowed = array_intersect_key($data, array_flip(['name', 'title', 'description', 'cover', 'enabled']));

        if ($allowed === []) {
            return ApiResponse::error('No supported fields', 422);
        }

        if (isset($allowed['title'])) {
            $allowed['name'] = $allowed['title'];
            unset($allowed['title']);
        }

        $sets = implode(', ', array_map(static fn (string $key): string => $key . ' = ?', array_keys($allowed)));
        $params = array_values($allowed);
        $params[] = time();
        $params[] = (int) $id;

        Db::execute(sprintf('UPDATE series SET %s, updated_at = ? WHERE id = ?', $sets), $params);

        return ApiResponse::json(['data' => Db::first('SELECT * FROM series WHERE id = ?', [(int) $id])]);
    }

    public function destroy(ApiRequest $request, string $id): ApiResponse
    {
        Db::execute('DELETE FROM episodes WHERE series_id = ?', [(int) $id]);
        $deleted = Db::execute('DELETE FROM series WHERE id = ?', [(int) $id]);

        return $deleted > 0 ? ApiResponse::json(['success' => true]) : ApiResponse::error('Series not found', 404);
    }

    public function episodes(ApiRequest $request, string $id): ApiResponse
    {
        return ApiResponse::json([
            'data' => Db::select('SELECT * FROM episodes WHERE series_id = ? ORDER BY season_num, episode_num', [(int) $id]),
        ]);
    }
}
