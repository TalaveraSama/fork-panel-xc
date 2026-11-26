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
            'INSERT INTO series (name, description, cover, tmdb_id, overview, backdrop, rating, genres, original_title, first_air_date, enabled, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                (string) ($data['name'] ?? $data['title']),
                $this->text($data['overview'] ?? $data['description'] ?? null),
                $this->text($data['cover'] ?? $data['stream_icon'] ?? null),
                $this->integer($data['tmdb_id'] ?? null),
                $this->text($data['overview'] ?? null),
                $this->text($data['backdrop'] ?? null),
                $this->decimal($data['rating'] ?? null),
                $this->genres($data['genres'] ?? null),
                $this->text($data['original_title'] ?? null),
                $this->text($data['first_air_date'] ?? null),
                array_key_exists('enabled', $data) ? (!empty($data['enabled']) ? 1 : 0) : 1,
                time(),
                time(),
            ]
        );

        return ApiResponse::json(['data' => Db::first('SELECT * FROM series WHERE id = ?', [$id])], 201);
    }

    public function update(ApiRequest $request, string $id): ApiResponse
    {
        $data = $request->json();
        $allowed = array_intersect_key($data, array_flip([
            'name', 'title', 'description', 'overview', 'cover', 'stream_icon', 'backdrop', 'rating',
            'genres', 'original_title', 'first_air_date', 'tmdb_id', 'enabled',
        ]));

        if ($allowed === []) {
            return ApiResponse::error('No supported fields', 422);
        }

        if (isset($allowed['title'])) {
            $allowed['name'] = $allowed['title'];
            unset($allowed['title']);
        }

        if (isset($allowed['stream_icon'])) {
            $allowed['cover'] = $allowed['stream_icon'];
            unset($allowed['stream_icon']);
        }

        foreach (['description', 'overview', 'cover', 'backdrop', 'original_title', 'first_air_date'] as $field) {
            if (array_key_exists($field, $allowed)) {
                $allowed[$field] = $this->text($allowed[$field]);
            }
        }

        if (array_key_exists('rating', $allowed)) {
            $allowed['rating'] = $this->decimal($allowed['rating']);
        }

        if (array_key_exists('genres', $allowed)) {
            $allowed['genres'] = $this->genres($allowed['genres']);
        }

        if (array_key_exists('tmdb_id', $allowed)) {
            $allowed['tmdb_id'] = $this->integer($allowed['tmdb_id']);
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

    public function storeEpisode(ApiRequest $request, string $id): ApiResponse
    {
        $seriesId = (int) $id;

        if (Db::first('SELECT id FROM series WHERE id = ?', [$seriesId]) === null) {
            return ApiResponse::error('Series not found', 404);
        }

        $data = $request->json();
        $errors = $this->require($data, ['title', 'source']);

        if ($errors !== null) {
            return ApiResponse::error('Validation failed', 422, $errors);
        }

        $episodeId = Db::insert(
            'INSERT INTO episodes (series_id, season_num, episode_num, title, source, duration, enabled, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $seriesId,
                max(1, (int) ($data['season_num'] ?? 1)),
                max(1, (int) ($data['episode_num'] ?? 1)),
                (string) $data['title'],
                (string) $data['source'],
                (int) ($data['duration'] ?? 0),
                !empty($data['enabled']) ? 1 : 0,
                time(),
            ]
        );

        return ApiResponse::json([
            'data' => Db::first('SELECT * FROM episodes WHERE id = ?', [$episodeId]),
        ], 201);
    }

    private function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function integer(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $int = (int) $value;

        return $int > 0 ? $int : null;
    }

    private function decimal(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 1);
    }

    private function genres(mixed $value): string
    {
        if (is_array($value)) {
            return implode(', ', array_map('strval', $value));
        }

        return is_scalar($value) ? trim((string) $value) : '';
    }
}
