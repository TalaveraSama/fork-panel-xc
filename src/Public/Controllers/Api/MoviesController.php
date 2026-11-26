<?php

declare(strict_types=1);

namespace ForkPanelXc\Public\Controllers\Api;

use ForkPanelXc\Core\Http\ApiRequest;
use ForkPanelXc\Core\Http\ApiResponse;
use ForkPanelXc\Core\Support\Db;

/**
 * VOD movies. Movies live in the `streams` table with stream_type = 'movie'.
 */
class MoviesController extends BaseApiController
{
    public function index(ApiRequest $request): ApiResponse
    {
        [$page, $perPage, $offset] = $this->pagination($request);
        [$field, $direction] = $this->sort($request, ['id', 'stream_display_name', 'year', 'enabled']);

        $where = ["stream_type = 'movie'"];
        $params = [];

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $where[] = 'stream_display_name LIKE ?';
            $params[] = '%' . $search . '%';
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

        if (empty($data['name']) && empty($data['stream_display_name'])) {
            return ApiResponse::error('Title is required', 422, ['name' => ['Title is required']]);
        }

        if (empty($data['stream_source'])) {
            return ApiResponse::error('Source URL is required', 422, ['stream_source' => ['Source URL is required']]);
        }

        $id = Db::insert(
            'INSERT INTO streams (stream_display_name, stream_source, stream_icon, stream_type, category_id, year, tmdb_id,
                                  overview, backdrop, rating, runtime, genres, original_title, enabled, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                (string) ($data['name'] ?? $data['stream_display_name']),
                (string) $data['stream_source'],
                (string) ($data['stream_icon'] ?? ''),
                'movie',
                isset($data['category_id']) && $data['category_id'] !== '' ? (int) $data['category_id'] : null,
                isset($data['year']) && $data['year'] !== '' ? (int) $data['year'] : null,
                isset($data['tmdb_id']) && $data['tmdb_id'] !== '' ? (int) $data['tmdb_id'] : null,
                $this->text($data['overview'] ?? null),
                $this->text($data['backdrop'] ?? null),
                $this->decimal($data['rating'] ?? null),
                $this->integer($data['runtime'] ?? null),
                $this->genres($data['genres'] ?? null),
                $this->text($data['original_title'] ?? null),
                array_key_exists('enabled', $data) ? (!empty($data['enabled']) ? 1 : 0) : 1,
                time(),
                time(),
            ]
        );

        return ApiResponse::json(['data' => $this->find($id)], 201);
    }

    public function show(ApiRequest $request, string $id): ApiResponse
    {
        $movie = $this->find((int) $id);

        return $movie === null
            ? ApiResponse::error('Movie not found', 404)
            : ApiResponse::json(['data' => $movie]);
    }

    public function update(ApiRequest $request, string $id): ApiResponse
    {
        $movieId = (int) $id;

        if ($this->find($movieId) === null) {
            return ApiResponse::error('Movie not found', 404);
        }

        $data = $request->json();
        $allowed = array_intersect_key($data, array_flip([
            'name', 'stream_display_name', 'stream_source', 'stream_icon', 'category_id', 'year', 'tmdb_id',
            'overview', 'backdrop', 'rating', 'runtime', 'genres', 'original_title', 'enabled',
        ]));

        if ($allowed === []) {
            return ApiResponse::json(['data' => $this->find($movieId)]);
        }

        if (isset($allowed['name'])) {
            $allowed['stream_display_name'] = $allowed['name'];
            unset($allowed['name']);
        }

        foreach (['overview', 'backdrop', 'original_title'] as $field) {
            if (array_key_exists($field, $allowed)) {
                $allowed[$field] = $this->text($allowed[$field]);
            }
        }

        if (array_key_exists('rating', $allowed)) {
            $allowed['rating'] = $this->decimal($allowed['rating']);
        }

        if (array_key_exists('runtime', $allowed)) {
            $allowed['runtime'] = $this->integer($allowed['runtime']);
        }

        if (array_key_exists('genres', $allowed)) {
            $allowed['genres'] = $this->genres($allowed['genres']);
        }

        $sets = implode(', ', array_map(static fn (string $key): string => $key . ' = ?', array_keys($allowed)));
        $params = array_values($allowed);
        $params[] = time();
        $params[] = $movieId;

        Db::execute(sprintf('UPDATE streams SET %s, updated_at = ? WHERE id = ?', $sets), $params);

        return ApiResponse::json(['data' => $this->find($movieId)]);
    }

    public function destroy(ApiRequest $request, string $id): ApiResponse
    {
        $deleted = Db::execute("DELETE FROM streams WHERE id = ? AND stream_type = 'movie'", [(int) $id]);

        return $deleted > 0
            ? ApiResponse::json(['success' => true])
            : ApiResponse::error('Movie not found', 404);
    }

    private function find(int $id): ?array
    {
        return Db::first(
            "SELECT s.*, c.category_name FROM streams s LEFT JOIN categories c ON c.id = s.category_id
             WHERE s.id = ? AND s.stream_type = 'movie'",
            [$id]
        );
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
