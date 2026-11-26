<?php

declare(strict_types=1);

namespace ForkPanelXc\Public\Controllers\Api;

use ForkPanelXc\Core\Http\ApiRequest;
use ForkPanelXc\Core\Http\ApiResponse;
use ForkPanelXc\Core\Support\Db;

class EpgController extends BaseApiController
{
    public function index(ApiRequest $request): ApiResponse
    {
        return ApiResponse::json([
            'data' => [
                'sources' => (int) Db::column('SELECT COUNT(*) FROM epg_sources'),
                'channels' => (int) Db::column('SELECT COUNT(*) FROM epg_channels'),
                'last_sync' => Db::column('SELECT MAX(last_sync) FROM epg_sources'),
            ],
        ]);
    }

    public function sources(ApiRequest $request): ApiResponse
    {
        return ApiResponse::json(['data' => Db::select('SELECT * FROM epg_sources ORDER BY id DESC')]);
    }

    public function storeSource(ApiRequest $request): ApiResponse
    {
        $data = $request->json();
        $errors = $this->require($data, ['name', 'url']);

        if ($errors !== null) {
            return ApiResponse::error('Validation failed', 422, $errors);
        }

        $id = Db::insert(
            'INSERT INTO epg_sources (name, url, enabled, last_sync, created_at) VALUES (?, ?, ?, NULL, ?)',
            [(string) $data['name'], (string) $data['url'], !empty($data['enabled']) ? 1 : 0, time()]
        );

        return ApiResponse::json(['data' => Db::first('SELECT * FROM epg_sources WHERE id = ?', [$id])], 201);
    }

    public function updateSource(ApiRequest $request, string $id): ApiResponse
    {
        $data = $request->json();
        $allowed = array_intersect_key($data, array_flip(['name', 'url', 'enabled']));

        if ($allowed === []) {
            return ApiResponse::error('No supported fields', 422);
        }

        $sets = implode(', ', array_map(static fn (string $key): string => $key . ' = ?', array_keys($allowed)));
        $params = array_values($allowed);
        $params[] = (int) $id;

        Db::execute(sprintf('UPDATE epg_sources SET %s WHERE id = ?', $sets), $params);

        return ApiResponse::json(['data' => Db::first('SELECT * FROM epg_sources WHERE id = ?', [(int) $id])]);
    }

    public function deleteSource(ApiRequest $request, string $id): ApiResponse
    {
        $deleted = Db::execute('DELETE FROM epg_sources WHERE id = ?', [(int) $id]);

        return $deleted > 0 ? ApiResponse::json(['success' => true]) : ApiResponse::error('Source not found', 404);
    }

    public function syncSource(ApiRequest $request, string $id): ApiResponse
    {
        $source = Db::first('SELECT * FROM epg_sources WHERE id = ?', [(int) $id]);

        if ($source === null) {
            return ApiResponse::error('Source not found', 404);
        }

        // The panel's cron:epg job performs the download; record the request.
        Db::execute('UPDATE epg_sources SET last_sync = ? WHERE id = ?', [time(), (int) $id]);

        return ApiResponse::json(['success' => true, 'message' => 'EPG refresh queued']);
    }

    public function channels(ApiRequest $request): ApiResponse
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
        $total = (int) Db::column('SELECT COUNT(*) FROM epg_channels WHERE ' . $whereSql, $params);
        $rows = Db::select(
            sprintf('SELECT * FROM epg_channels WHERE %s ORDER BY name ASC LIMIT %d OFFSET %d', $whereSql, $perPage, $offset),
            $params
        );

        return ApiResponse::json($this->paginated($rows, $total, $page, $perPage));
    }
}
